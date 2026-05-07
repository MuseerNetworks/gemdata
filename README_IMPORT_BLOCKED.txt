GemData import is currently blocked.

Source selected:
- D:\Desktop\Museer\htdocs\gemdata\gem.data

Fallback reference:
- D:\Desktop\Museer\htdocs\_backup\gem.data_20260228_1428

Why import is blocked:
- The source files are encrypted with Windows EFS.
- The available account cannot decrypt or read files such as:
  - index.php
  - composer.json
  - README.md
  - includes\db.php
  - database\schema.sql
- Copy operations to C:\xampp\htdocs\gemdata fail with "Access is denied."

What was verified:
- C:\xampp\htdocs exists.
- C:\xampp\htdocs\gemdata was created as the intended deployment target.
- The real GemData project structure appears to be inside D:\Desktop\Museer\htdocs\gemdata\gem.data.
- The sibling files in D:\Desktop\Museer\htdocs\gemdata look like separate prototype assets and were not copied.

Next required action:
- Decrypt D:\Desktop\Museer\htdocs\gemdata\gem.data with the original Windows certificate owner
  or provide an unencrypted copy of the GemData project.

After decryption, complete these steps:
- Copy the project into C:\xampp\htdocs\gemdata
- Normalize the app structure
- Fix internal paths/includes
- Configure MySQL connection for XAMPP
- Import database schema
- Run and verify http://localhost/gemdata
