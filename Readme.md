Allows us to define `PC_GF_EMAIL_BLACKLIST` in our workload file and block the emails listed. The form will look like it was filled out but the entry is automatically sent to spam so the spammer is wasting their time.

```yaml
-  name: PC_GF_EMAIL_BLACKLIST
   value: email@one.com,email@two.com
```

Added ability to blacklist a domain with `PC_GF_DOMAIN_BLACKLIST`

```yaml
-  name: PC_GF_DOMAIN_BLACKLIST
   value: one.com,two.com
```
