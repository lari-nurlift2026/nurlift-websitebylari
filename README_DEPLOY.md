# Nurlift site V1 — Deploy

## Files
- `/index.html`: Portuguese homepage
- `/en/index.html`: English homepage
- `/styles.css`: visual styles
- `/script.js`: smooth scrolling
- `/robots.txt`, `/sitemap.xml`, `/llms.txt`, `/ai.json`: discovery/SEO/GEO support
- `/lovable_prompt.txt`: paste into a new Lovable project

## Plesk — fastest path (File Manager)
1. Back up the current website.
2. In Plesk: Websites & Domains > nurlift.com > Files.
3. Open `httpdocs` (default document root unless your hosting settings show another root).
4. Rename the current site folder/files or download a backup before replacing anything.
5. Upload the contents of this package into `httpdocs`, preserving the `/en/` folder.
6. Confirm that `index.html` is at `httpdocs/index.html` and English is at `httpdocs/en/index.html`.
7. Open https://nurlift.com/ and https://nurlift.com/en/.
8. Verify SSL and preferred domain in Hosting Settings.

## Plesk — SSH option
Replace USER and HOST with the hosting credentials shown by Plesk.

```bash
ssh USER@HOST
cd httpdocs
mkdir -p ../backup-$(date +%Y%m%d-%H%M)
cp -a . ../backup-$(date +%Y%m%d-%H%M)/
```

From your local machine, upload the extracted site:

```bash
scp -r nurlift-site-v1/* USER@HOST:httpdocs/
```

If you are already inside the Plesk webspace through chrooted SSH, `httpdocs` is commonly visible directly under your home/root.

## Lovable
Lovable currently does not start a new project by importing an existing GitHub/codebase. Create a NEW Lovable project and paste the contents of `lovable_prompt.txt` as the first prompt.

After Lovable generates the project:
1. Compare it with this HTML/copy.
2. Ask Lovable to preserve the information architecture and PT/EN routes.
3. Connect GitHub in Settings > Connectors > GitHub if you want version control/self-hosting.
4. Lovable syncs its project to GitHub; you can then deploy externally or continue in Lovable.

## Recommended production workflow
For this immediate MVP, the static Plesk version is the fastest route.
Use Lovable in parallel to iterate on the visual/UI version. When Lovable's version is approved, connect it to GitHub and replace the static MVP with the built production output or host Lovable directly.

## Before production
- Confirm the real commercial contact email; this V1 currently uses `hello@nurlift.com` as a placeholder.
- Add legal/privacy links.
- Add analytics/tag manager if needed.
- Add final logo/assets and favicon.
- Validate all claims about Dropper capabilities before publishing.
