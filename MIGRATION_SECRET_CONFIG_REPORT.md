# Secret/config migration report

This package is based on the new source package and has old-version private configuration/data merged in.

Migrated without printing secret values:
- AIF Chat `.env` created from the new example and filled with old DB/JWT/SMTP/site/MiMo values where available.
- Root DB, SMTP and payment defaults filled from the old package while keeping environment-variable overrides.
- Admin login hash/users restored.
- Private AI provider keys restored, including MiMo/OpenRouter/etc., while keeping environment-variable overrides.
- Private model credentials restored in `data/ai_private.php` and model-level credentials patched into `config/spark_lite.php`.
- Data JSON state/config restored from the old package.
- `data/jwt_secret.key` restored.

Do not commit or publish this package because it contains real private credentials.
