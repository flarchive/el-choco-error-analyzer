from pathlib import Path
import hashlib
import json
import zipfile

root = Path(__file__).resolve().parent.parent
version = "0.1.0-alpha.1"
destination = root / "dist"
destination.mkdir(exist_ok=True)
archive = destination / f"error-analyzer-{version}.zip"
top_files = {
    "composer.json", "extend.php", "LICENSE", "README.md", "README.en.md",
    "INSTALL-DE.md", "INSTALL-EN.md", "CHANGELOG.md", ".gitignore", ".gitattributes",
}
folders = {"src", "less", "locale", "js", "tests", "resources"}
files = []
for file in sorted(root.rglob("*")):
    relative = file.relative_to(root)
    if any(part in {"node_modules", "vendor", "dist", "__pycache__", ".git"} for part in relative.parts[:-1]) and not (
        relative.parts[:2] == ("js", "dist") and len(relative.parts) == 3
    ):
        continue
    if not file.is_file() or file.is_symlink():
        continue
    if (len(relative.parts) == 1 and file.name in top_files) or relative.parts[0] in folders:
        files.append(file)

with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as output:
    for file in files:
        name = f"error-analyzer-{version}/" + file.relative_to(root).as_posix()
        info = zipfile.ZipInfo(name)
        info.create_system = 3
        info.external_attr = 0o100644 << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        output.writestr(info, file.read_bytes())

with zipfile.ZipFile(archive) as output:
    assert output.testzip() is None
    prefix = f"error-analyzer-{version}/"
    for name in output.namelist():
        assert name.startswith(prefix) and ".." not in Path(name).parts
        assert "node_modules" not in Path(name).parts
        assert output.read(name) == (root / name.removeprefix(prefix)).read_bytes()
    manifest = json.loads(output.read(prefix + "composer.json"))
    assert manifest["name"] == "el-choco/error-analyzer"
    assert output.read(prefix + "js/dist/admin.js")

checksum = hashlib.sha256(archive.read_bytes()).hexdigest()
archive.with_suffix(".zip.sha256").write_text(checksum + "  " + archive.name + "\n", encoding="ascii")
print(f"{archive}\n{len(files)} files, {archive.stat().st_size} bytes; archive contents verified.")
