Allows us to define `PC_GF_EMAIL_BLACKLIST` or `PC_GF_DOMAIN_BLACKLIST` in our workload file and block the emails listed. The form will look like it was filled out but the entry is automatically sent to spam so the spammer is wasting their time. The plugin uses `getenv()` so you can also define a `.env` file with these values and blacklist items.

```yaml
-  name: PC_GF_EMAIL_BLACKLIST
   value: email@one.com,email@two.com
```

Added ability to blacklist a domain with `PC_GF_DOMAIN_BLACKLIST`

```yaml
-  name: PC_GF_DOMAIN_BLACKLIST
   value: one.com,two.com
```

Entries are trimmed and case-insensitive, so `one.com, Two.com` works the same as `one.com,two.com`.

## Block by IP or country

Define `PC_GF_IP_BLACKLIST` to send entries from specific IPs or ranges to spam. It takes a comma separated list of single IPs and CIDR ranges, IPv4 and IPv6. The submitter IP comes from the entry, or from the request when the form doesn't store IPs.

```yaml
-  name: PC_GF_IP_BLACKLIST
   value: 203.0.113.7,198.51.100.0/24,2001:db8::/32
```

Define `PC_GF_ALLOWED_COUNTRIES` to send entries from any other country to spam. It takes two letter country codes, case-insensitive.

```yaml
-  name: PC_GF_ALLOWED_COUNTRIES
   value: US
```

Define `PC_GF_IP_ALLOWLIST` for IPs or ranges that skip the IP blacklist and country checks, for example to test a country restricted site.

```yaml
-  name: PC_GF_IP_ALLOWLIST
   value: 207.6.84.153
```

Notes:

- The country comes from the `X-PC-Country` header set by our ingress. If the country is unknown (`--`, `-`, empty or missing header) the entry is allowed.
- The GeoIP map is IPv4 only, so some IPv6 visitors have an unknown country and are allowed.
- The allowlist skips only the IP and country checks. Email and domain checks still apply.
- Invalid entries (bad IPs, bad prefixes) are ignored.
- Country codes are ISO 3166-1 alpha-2, comma separated: `GB` not `UK`, and US territories (`PR`, `GU`, `VI`, `AS`, `MP`) are separate codes. Anything that isn't two letters is ignored and logged. If nothing valid is left (`USA`, `US CA`) the country check is off rather than sending every entry to spam.
- Ranges broader than `/8` (IPv4) or `/16` (IPv6) are ignored, so a typo like `0.0.0.0/0` can't match everyone.
- The IP checks only work when the entry IP is the visitor's real IP. On a Cloudflare-proxied site, check the host is in the ingress `cf-proxied-hosts` list first, or entries show Cloudflare edge IPs and the country is the edge's.
- Unset variables turn the feature off.
