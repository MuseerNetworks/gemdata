# GemData Production Operator Shortlist

Use this when you need the smallest possible deployment/recovery flow in cPanel.

## Deploy in 5 minutes
1. Open [`.cpanel.yml`](C:/xampp/htdocs/gemdata/.cpanel.yml) and confirm the target path is your real cPanel account, for example `/home/yyrigitd/public_html/`.
2. In cPanel, open `Git Version Control`.
3. Click `Update from Remote`.
4. Click `Deploy HEAD Commit`.
5. Open:
   - `https://gemdata.com.ng/`
   - `https://gemdata.com.ng/user/login.php`
   - `https://gemdata.com.ng/admin/login.php`

## If you get HTTP 500
1. Rename `public_html/.htaccess` to `.htaccess.off`.
2. Reload `https://gemdata.com.ng/`.
3. If it still fails, open:
   - `cPanel > Metrics > Errors`
   - `public_html/.user.ini`
   - `public_html/error_log` if present
4. Open one of these direct probes if deployed:
   - `https://gemdata.com.ng/fallback_test.php`
   - `https://gemdata.com.ng/emergency-index.php`
   - `https://gemdata.com.ng/bootstrap_probe.php`
5. Confirm `MultiPHP Manager` shows PHP `8.3` for `gemdata.com.ng`.

## Minimum success signal
- Homepage loads
- User login loads
- Admin login loads
- No raw `500`
- No new fatal errors in cPanel `Errors`
