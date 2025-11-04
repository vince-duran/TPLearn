# GitHub Pages DNS Configuration for tplearn.tech
# Fix for DNS check unsuccessful error

## Required DNS Records for GitHub Pages

### A Records (Required)
Add these 4 A records pointing to GitHub Pages servers:

```
Type: A
Host: @ (or leave blank)
Points to: 185.199.108.153
TTL: 3600

Type: A  
Host: @ (or leave blank)
Points to: 185.199.109.153
TTL: 3600

Type: A
Host: @ (or leave blank)  
Points to: 185.199.110.153
TTL: 3600

Type: A
Host: @ (or leave blank)
Points to: 185.199.111.153
TTL: 3600
```

### AAAA Records (IPv6 - Optional but recommended)
```
Type: AAAA
Host: @ (or leave blank)
Points to: 2606:50c0:8000::153
TTL: 3600

Type: AAAA
Host: @ (or leave blank)
Points to: 2606:50c0:8001::153
TTL: 3600

Type: AAAA  
Host: @ (or leave blank)
Points to: 2606:50c0:8002::153
TTL: 3600

Type: AAAA
Host: @ (or leave blank)
Points to: 2606:50c0:8003::153
TTL: 3600
```

### CNAME Record for www
```
Type: CNAME
Host: www
Points to: vince-duran.github.io
TTL: 3600
```

## Quick Fix Steps:

1. Delete the current A record pointing to 203.168.176.23
2. Add the 4 GitHub Pages A records above
3. Update the www CNAME to point to vince-duran.github.io
4. Wait 24-48 hours for DNS propagation
5. Click "Check again" in GitHub Pages settings

## Verification:
After DNS propagation, these commands should work:
- nslookup tplearn.tech
- nslookup www.tplearn.tech

The domain should resolve to one of the GitHub Pages IP addresses.