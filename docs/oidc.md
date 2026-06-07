# OIDC / Single Sign-On for PriceBuddy

PriceBuddy supports login via any standard **OpenID Connect (OIDC)** identity provider
(Keycloak, Authentik, Auth0, Okta, Azure AD, Zitadel, and others).

## How it works

PriceBuddy acts as an OIDC **client (relying party)**. When OIDC is configured, a
"Single Sign-On" button appears on the login page. Clicking it redirects to your IdP;
after successful authentication PriceBuddy creates or links the account by email and
logs the user in.

- **Auto-registration is on**: any user the IdP authenticates gets a PriceBuddy account.
- **Existing local admin keeps working**: the `APP_USER_EMAIL` admin can still log in
  with their password unless you explicitly set `DISABLE_LOCAL_LOGIN=true`.
- **Per-user data isolation is enforced**: each user only sees their own products, stores,
  and price history regardless of how they signed in.

## Required environment variables

| Variable | Required | Description |
|---|---|---|
| `OIDC_BASE_URL` | ✅ | IdP base URL; discovery doc fetched from `{base_url}/.well-known/openid-configuration` |
| `OIDC_CLIENT_ID` | ✅ | Client ID registered at the IdP |
| `OIDC_CLIENT_SECRET` | ✅ | Client secret (keep this safe — never commit it) |
| `OIDC_REDIRECT_URI` | ✅ | Must be `https://<your-app>/admin/oauth/callback/oidc` |
| `OIDC_SCOPES` | optional | Space-separated scopes (default: `openid profile email`) |
| `OIDC_ADMIN_GROUP` | optional | Group value that grants admin access |
| `OIDC_GROUPS_CLAIM` | optional | Claim name containing groups (default: `groups`) |
| `OIDC_BUTTON_LABEL` | optional | Login button label (default: `Single Sign-On`) |
| `OIDC_VERIFY_JWT` | optional | Validate ID-token signatures (default: `true`) |
| `OIDC_JWT_PUBLIC_KEY` | optional | PEM public key if IdP has no JWKS endpoint |
| `DISABLE_LOCAL_LOGIN` | optional | Set `true` to disable email/password login entirely |

## Redirect URI

Register this URI at your IdP exactly:

```
https://<your-pricebuddy-url>/admin/oauth/callback/oidc
```

## Admin access via groups

By default all OIDC users are regular users (they can manage only their own data).
To grant admin access (user management + global settings):

1. Set `OIDC_ADMIN_GROUP=<group-name>` to the name/value of the group in your IdP.
2. Configure your IdP to emit a `groups` claim (or set `OIDC_GROUPS_CLAIM` to the correct claim name).
3. Add the appropriate IdP-specific scope (e.g. `groups` for Keycloak/Authentik) to `OIDC_SCOPES`.

Admin status is synced on every login — removing a user from the group revokes admin on their next sign-in. The `APP_USER_EMAIL` bootstrap admin is always protected from demotion.

## Disabling local login

Set `DISABLE_LOCAL_LOGIN=true` to force all logins through OIDC. The email/password form is hidden and the Livewire endpoint is blocked.

⚠️ **Warning**: Verify OIDC is working before enabling this. If OIDC stops working, recovery requires setting `DISABLE_LOCAL_LOGIN=false` (e.g. via `docker compose down && edit .env && docker compose up -d`).

## Security notes

- **Verified emails strongly recommended**: PriceBuddy links OIDC identities to local accounts by email address. If your IdP allows *unverified* email addresses, an attacker could register a fake account with someone else's email and gain access to their PriceBuddy data. Enforce email verification at the IdP level.
- **Keep `OIDC_CLIENT_SECRET` private**: never commit it to the repository; pass it only via environment variables or Docker secrets.
- **JWT signature verification**: `OIDC_VERIFY_JWT=true` (the default) validates the ID-token signature. Only disable this if your IdP does not support JWKS.

## Keycloak example

1. Create a realm (e.g. `homelab`).
2. Create a client: client ID = `pricebuddy`, client type = `OpenID Connect`, client authentication = On.
3. In the client's *Settings*: add `https://<your-app>/admin/oauth/callback/oidc` as a valid redirect URI.
4. In *Client scopes*: add a `groups` scope that maps the group membership claim to the token.
5. Set env vars:
   ```
   OIDC_BASE_URL=https://keycloak.example.com/realms/homelab
   OIDC_CLIENT_ID=pricebuddy
   OIDC_CLIENT_SECRET=<from the Credentials tab>
   OIDC_REDIRECT_URI=https://pricebuddy.example.com/admin/oauth/callback/oidc
   OIDC_SCOPES=openid profile email groups
   OIDC_ADMIN_GROUP=pricebuddy-admins
   ```

## Authentik example

1. Create a Provider: type = OAuth2/OIDC, client type = Confidential.
2. Add `https://<your-app>/admin/oauth/callback/oidc` as a redirect URI.
3. Create an Application bound to this provider.
4. Add a Property Mapping that emits group names as `groups` in the token.
5. Set env vars:
   ```
   OIDC_BASE_URL=https://authentik.example.com/application/o/<application-slug>
   OIDC_CLIENT_ID=<client id from provider>
   OIDC_CLIENT_SECRET=<client secret from provider>
   OIDC_REDIRECT_URI=https://pricebuddy.example.com/admin/oauth/callback/oidc
   OIDC_SCOPES=openid profile email
   OIDC_ADMIN_GROUP=pricebuddy-admins
   OIDC_GROUPS_CLAIM=groups
   ```
