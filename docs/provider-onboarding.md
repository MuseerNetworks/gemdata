# Provider Onboarding

## Supported adapters
- SMEPlug
- VTpass
- ClubKonnect
- AlrahuzData
- EasyAccessAPI

## Steps
1. Add credentials only in `/home/<cpanel_user>/gemdata-config.php`
2. Keep `enabled => false` until connectivity is verified
3. Update the matching `provider_accounts` row in Admin Providers
4. Set priority order, fallback support, supported services, and low-balance threshold
5. Run a connection test from `admin/providers.php`
6. Enable one provider at a time and verify health before enabling failover

## Guardrails
- Never place provider secrets in repo files
- Keep sandbox mode on until live validation is complete
- Do not activate multiple live providers until the primary route is verified
