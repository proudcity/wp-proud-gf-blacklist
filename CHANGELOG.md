# Changelog

## 2026.11.17.1

- Added `PC_GF_IP_BLACKLIST` to send entries from listed IPs or CIDR ranges (IPv4 and IPv6) to spam.
- Added `PC_GF_ALLOWED_COUNTRIES` to send entries from countries not listed to spam, using the ingress `X-PC-Country` header. Unknown countries are allowed.
- Added `PC_GF_IP_ALLOWLIST` to skip the IP and country checks for listed IPs.
- IP and country hits record a reason in the Gravity Forms spam note.

Refs proudcity/wp-proudcity#2377
