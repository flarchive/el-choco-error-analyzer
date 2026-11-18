<?php

require $argv[1];
spl_autoload_register(function (string $class): void {
    $prefix = 'ElChoco\\ErrorAnalyzer\\';
    if (str_starts_with($class, $prefix)) {
        require __DIR__.'/../src/'.substr($class, strlen($prefix)).'.php';
    }
});

use ElChoco\ErrorAnalyzer\AnalyzeController;
use ElChoco\ErrorAnalyzer\Analyzer;
use ElChoco\ErrorAnalyzer\LogParser;
use ElChoco\ErrorAnalyzer\PackageInventory;
use ElChoco\ErrorAnalyzer\Redactor;
use Flarum\Foundation\Paths;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Flarum\Settings\MemoryCacheSettingsRepository;
use Flarum\User\Access\Gate;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\Guest;
use Flarum\User\User;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Laminas\Diactoros\ServerRequest;

$checks = 0;
function check(bool $result, string $label): void
{
    global $checks;
    $checks++;
    if (!$result) {
        throw new RuntimeException($label);
    }
}

$base = str_replace('\\', '/', sys_get_temp_dir()).'/error-analyzer-test-'.bin2hex(random_bytes(6));
foreach (['storage/logs', 'vendor/composer', 'public'] as $folder) {
    mkdir($base.'/'.$folder, 0700, true);
}
$packages = ['packages' => [
    ['name' => 'flarum/core', 'version' => 'v2.0.2', 'type' => 'library', 'install-path' => '../flarum/core'],
    ['name' => 'acme/flarum-ext-sample', 'version' => '1.2.3', 'type' => 'flarum-extension', 'install-path' => '../acme/flarum-ext-sample'],
    ['name' => 'acme/local', 'version' => 'dev-main', 'type' => 'flarum-extension', 'install-path' => '../../workbench/local'],
    ['name' => 'acme/disabled', 'version' => '2.0.0', 'type' => 'flarum-extension'],
    ['name' => 'vendor/library', 'version' => '3.0.0', 'type' => 'library'],
]];
file_put_contents($base.'/vendor/composer/installed.json', json_encode($packages));
$inventory = new PackageInventory();
$installed = $inventory->read($base.'/vendor', ['acme-sample']);
check($installed['available'], 'Inventory available');
check($installed['packages']['acme/flarum-ext-sample']['enabled'], 'Flarum extension ID convention');
check(!$installed['packages']['acme/disabled']['enabled'], 'Disabled extension state');
check($installed['packages']['flarum/core']['type'] === 'core', 'Core classification');
check($installed['packages']['vendor/library']['type'] === 'library', 'Library classification');
check(count($inventory->mentioned($base.'/vendor/acme/flarum-ext-sample/src/A.php', $installed['packages'])) === 1, 'Vendor path attribution');
check(count($inventory->mentioned($base.'/workbench/local/src/A.php', $installed['packages'])) === 1, 'Local Composer install-path attribution');
check($inventory->mentioned($base.'/vendor/acme/disabled-extra/src/A.php', $installed['packages']) === [], 'Package path boundaries');

$redactor = new Redactor($base, $base.'/storage');
$secretText = 'password="let me in" API_KEY=very-secret session_id=xyz user_id=12345 email=hello@example.test '.
    'Bearer abcdefghijklmn Basic YTpi http://alice:secret@private.example/path?token=secret '.
    '192.168.2.3 2001:db8::1234 ghp_123456abcdef eyJabcdef.eyJsecret.signature '.
    "-----BEGIN RSA PRIVATE KEY-----\nsecret-material\n-----END RSA PRIVATE KEY-----\n".
    $base.'/vendor/acme/flarum-ext-sample/src/File.php /home/alice/secret/file.php';
$safe = $redactor->text($secretText);
foreach (['let me in', 'very-secret', 'xyz', '12345', 'hello@example.test', 'abcdefghijklmn', 'YTpi', 'private.example', '192.168.2.3', '2001:db8::1234', 'ghp_123456abcdef', 'eyJabcdef', 'secret-material', '/home/alice', $base] as $secret) {
    check(!str_contains($safe, $secret), 'Secret removed: '.$secret);
}
check(str_contains($safe, '[flarum]/vendor/acme/flarum-ext-sample/src/File.php'), 'Useful relative package path retained');
check(!str_contains($redactor->text('PDOException (SQL: select * from users where email = alice@example.test)'), 'select *'), 'SQL text omitted');

$trace = "\n#0 ".$base."/vendor/acme/flarum-ext-sample/src/Service.php(12): Acme\\Sample->run('argument-secret')".
    "\n#1 ".$base."/vendor/flarum/core/src/Http.php(22): Flarum\\Http::handle(Object(Request))\n#2 {main}";
$log = '[2026-11-18 10:00:00] production.ERROR: Missing item 123 for alice@example.test {"request":{"password":"context-secret"}}'.$trace."\n".
    '[2026-11-18 11:00:00] production.ERROR: Missing item 456 for bob@example.test {"cookie":"context-cookie"}'.$trace."\n".
    "[2026-11-18 12:00:00] production.WARNING: Mail transport unavailable [] []\n".
    "[2026-11-18 12:05:00] production.INFO: Ordinary status [] []\n".
    '[2026-11-18 12:10:00] production.ERROR: <script>alert("xss")</script> [] []'."\n";
$logFile = $base.'/storage/logs/flarum-2026-11-18.log';
file_put_contents($logFile, $log);
$before = hash_file('sha256', $logFile);
$analyzer = new Analyzer();
$result = $analyzer->scan($base, $base.'/storage', $base.'/vendor', ['acme-sample'], 5, 'WARNING');
check($result['scan']['records'] === 5, 'Multiline entries counted once');
check($result['scan']['matched'] === 4, 'Minimum level filters entries');
check(count($result['groups']) === 3, 'Repeated errors grouped across numeric identifiers and email');
$repeated = array_values(array_filter($result['groups'], fn (array $group): bool => $group['count'] === 2))[0];
check($repeated['first'] === '2026-11-18T10:00:00Z' && $repeated['last'] === '2026-11-18T11:00:00Z', 'Group time bounds');
check(count($repeated['packages']) === 2, 'Extension and core attributed');
check($repeated['packages'][0]['version'] === '1.2.3', 'Installed version included');
$payload = json_encode($result);
foreach (['argument-secret', 'context-secret', 'context-cookie', 'alice@example.test', 'bob@example.test', $base] as $secret) {
    check(!str_contains($payload, $secret), 'No raw sensitive data returned to client');
}
check(hash_file('sha256', $logFile) === $before, 'Log file remains unchanged');
check($result['environment']['flarum'] === 'v2.0.2', 'Environment core version');
check($result['environment']['phpWeb'] === PHP_VERSION, 'PHP version labelled as web runtime');
check(count($result['environment']['extensions']) === 3, 'Full extension list available for explicit export');
$errors = $analyzer->scan($base, $base.'/storage', $base.'/vendor', [], 5, 'ERROR');
check($errors['scan']['matched'] === 3, 'Errors-only scan');
$all = $analyzer->scan($base, $base.'/storage', $base.'/vendor', [], 5, 'DEBUG');
check($all['scan']['matched'] === 5, 'All-level scan');

$parser = new LogParser();
$functionTrace = $parser->excerpt("Error\n#0 /var/www/vendor/acme/sample/A.php(12): file_get_contents('function-secret')\n#1 Acme\\Sample::run('static-secret')");
check(!str_contains(implode('\n', $functionTrace['frames']), 'function-secret'), 'Global function arguments omitted');
check(!str_contains(implode('\n', $functionTrace['frames']), 'static-secret'), 'Standalone frame arguments omitted');
$malformed = $parser->parse('{"datetime":{"date":[]},"level_name":[],"message":"Malformed"}');
check($malformed === [], 'Malformed structured fields ignored');
$logger = new Monolog\Logger('flarum');
$handler = new Monolog\Handler\StreamHandler($base.'/storage/logs/monolog.log');
$handler->setFormatter(new Monolog\Formatter\LineFormatter(null, null, true, true));
$logger->pushHandler($handler);
$logger->error('Real Monolog exception', ['exception' => new RuntimeException('real-exception-secret'), 'password' => 'real-context-secret']);
$handler->close();
$real = $parser->parse(file_get_contents($base.'/storage/logs/monolog.log'));
check(count($real) === 1 && $real[0]['level'] === 'ERROR', 'Actual Monolog LineFormatter supported');
$realExcerpt = $parser->excerpt($real[0]['body']);
check(!str_contains(json_encode($realExcerpt), 'real-context-secret'), 'Actual Monolog context excluded');
check(str_contains(implode('\n', $realExcerpt['frames']), '.php'), 'Actual Monolog exception origin included');
unlink($base.'/storage/logs/monolog.log');
$origin = $parser->excerpt('Failure {"exception":"[object] (RuntimeException at '.$base.'/vendor/acme/disabled/src/Thing.php:50)"}');
check(str_contains($origin['frames'][0], '/acme/disabled/'), 'Exception origin retained even without stack frames');
$structured = json_encode(['datetime' => '2026-11-18T15:00:00+02:00', 'level_name' => 'ERROR', 'message' => 'Structured error',
    'context' => ['password' => 'json-secret', 'exception' => ['file' => $base.'/vendor/acme/local/src/One.php', 'trace' => ['#0 '.$base.'/vendor/flarum/core/src/App.php(30): App->run("json-argument")']]]]);
file_put_contents($base.'/storage/logs/json.log', $structured."\n");
$jsonResult = $analyzer->scan($base, $base.'/storage', $base.'/vendor', [], 5, 'ERROR');
check(count($jsonResult['groups']) === 3, 'JSON and multiline logs in same scan');
check(!str_contains(json_encode($jsonResult), 'json-secret'), 'JSON request context excluded');
check(!str_contains(json_encode($jsonResult), 'json-argument'), 'JSON stack arguments excluded');
$jsonGroup = array_values(array_filter($jsonResult['groups'], fn (array $group): bool => $group['message'] === 'Structured error'))[0];
check($jsonGroup['last'] === '2026-11-18T13:00:00Z', 'Timezone normalized to UTC');
file_put_contents($base.'/storage/logs/unknown.log', 'Arbitrary custom log contents');
file_put_contents($base.'/storage/logs/not-a-log.txt', '[2026-11-18 10:00:00] app.ERROR: Must not appear');
if (PHP_OS_FAMILY !== 'Windows') {
    symlink($base.'/vendor/composer/installed.json', $base.'/storage/logs/symlink.log');
}
$unknown = $analyzer->scan($base, $base.'/storage', $base.'/vendor', [], 5, 'WARNING');
check(in_array('unrecognized_log', $unknown['scan']['warnings'], true), 'Unknown formats reported');
check($unknown['scan']['filesFound'] === 3, 'Only regular .log files considered');
$empty = $analyzer->scan($base, $base.'/missing', $base.'/missing', [], 5, 'WARNING');
check(in_array('logs_unavailable', $empty['scan']['warnings'], true), 'Missing log folder reported');
check(in_array('inventory_unavailable', $empty['scan']['warnings'], true), 'Missing package metadata reported');

file_put_contents($base.'/storage/logs/large.log', str_repeat('x', 1100000)."\n[2026-11-18 13:00:00] app.ERROR: Tail entry [] []\n");
$large = $analyzer->scan($base, $base.'/storage', $base.'/vendor', [], 5, 'WARNING');
check($large['scan']['partial'], 'Truncated files marked partial');
check($large['scan']['bytes'] <= 8388608, 'Scan byte budget enforced');
check(count(array_filter($large['groups'], fn (array $group): bool => $group['message'] === 'Tail entry')) === 1, 'Tail read discards incomplete first entry');
file_put_contents($base.'/storage/logs/limits.log', implode("\n", array_map(fn (int $number): string => '[2026-11-18 16:00:00] app.ERROR: Distinct '.chr(65 + intdiv($number, 26)).chr(65 + $number % 26), range(0, 299)))."\n");
$limited = $analyzer->scan($base, $base.'/storage', $base.'/vendor', [], 5, 'WARNING');
check(count($limited['groups']) <= 200 && $limited['scan']['partial'], 'Group limit enforced and disclosed');

User::setGate(new Gate(new Container(), []));
$member = new User();
$member->id = 2;
$member->setRelation('groups', new Collection());
$adminGroup = new Group();
$adminGroup->id = Group::ADMINISTRATOR_ID;
$admin = new User();
$admin->id = 1;
$admin->setRelation('groups', new Collection([$adminGroup]));
$guest = new Guest();
$guest->setAttribute('groups', new Collection());
$settingsClass = new ReflectionClass(MemoryCacheSettingsRepository::class);
$settings = $settingsClass->newInstanceWithoutConstructor();
(new ReflectionProperty(MemoryCacheSettingsRepository::class, 'cache'))->setValue($settings, ['extensions_enabled' => '["acme-sample"]']);
(new ReflectionProperty(MemoryCacheSettingsRepository::class, 'isCached'))->setValue($settings, true);
$controller = new AnalyzeController(new Paths(['base' => $base, 'public' => $base.'/public', 'storage' => $base.'/storage']), $settings);
foreach ([$guest, $member] as $actor) {
    $denied = false;
    try {
        $controller->handle(RequestUtil::withActor(new ServerRequest(), $actor));
    } catch (PermissionDeniedException $exception) {
        $denied = true;
    }
    check($denied, 'Guest/member access denied');
}
$request = RequestUtil::withActor(new ServerRequest(), $admin);
$response = $controller->handle($request);
check($response->getStatusCode() === 200, 'Admin scan permitted');
check(str_contains($response->getHeaderLine('Cache-Control'), 'no-store'), 'Sensitive response is not cached');
check($response->getHeaderLine('X-Content-Type-Options') === 'nosniff', 'Response content type protected');
foreach ([['files' => '../secret'], ['files' => 0], ['files' => 100], ['minimum' => ['ERROR']], ['minimum' => 'arbitrary']] as $invalid) {
    check($controller->handle($request->withQueryParams($invalid))->getStatusCode() === 422, 'Invalid scan parameters rejected');
}
check(hash_file('sha256', $logFile) === $before, 'Controller leaves original log unchanged');

$container = new Container();
$container->instance('events', new Illuminate\Events\Dispatcher($container));
$container->instance(Paths::class, new Paths(['base' => $base, 'public' => $base.'/public', 'storage' => $base.'/storage']));
$container->instance(Flarum\Settings\SettingsRepositoryInterface::class, $settings);
$container->instance(Flarum\Http\RouteHandlerFactory::class, new Flarum\Http\RouteHandlerFactory($container));
$container->bind('flarum.api.routes', fn (): Flarum\Http\RouteCollection => new Flarum\Http\RouteCollection());
$manifest = json_decode(file_get_contents(__DIR__.'/../composer.json'), true);
$extension = new Flarum\Extension\Extension(dirname(__DIR__), $manifest);
check($extension->getId() === 'el-choco-error-analyzer', 'Normal Flarum package ID');
foreach (require __DIR__.'/../extend.php' as $extender) {
    $extender->extend($container, $extension);
}
$routes = $container->make('flarum.api.routes')->getRoutes();
$route = $routes['elchoco.error-analyzer.scan'];
check($route['method'] === 'GET' && $route['path'] === '/error-analyzer', 'API route registered through normal extender');
check($route['handler']($request, [])->getStatusCode() === 200, 'Registered route resolves controller dependencies');
$routeDenied = false;
try {
    $route['handler'](RequestUtil::withActor(new ServerRequest(), $guest), []);
} catch (PermissionDeniedException $exception) {
    $routeDenied = true;
}
check($routeDenied, 'Registered API route enforces authorization');

function cleanFixture(string $folder, string $root): void
{
    if (!str_starts_with($folder.'/', $root.'/')) {
        throw new RuntimeException('Invalid fixture cleanup path');
    }
    foreach (new DirectoryIterator($folder) as $entry) {
        if ($entry->isDot()) {
            continue;
        }
        if ($entry->isDir() && !$entry->isLink()) {
            cleanFixture(str_replace('\\', '/', $entry->getPathname()), $root);
        } else {
            unlink($entry->getPathname());
        }
    }
    rmdir($folder);
}
cleanFixture($base, $base);
echo $checks." checks passed with actual Flarum dependencies.\n";
