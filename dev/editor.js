// usage: node dev/editor.js <postId...>   (env: SITE_URL, WP_ADMIN_USER, WP_ADMIN_PASS, DRY=1)   -> opens each post in the block editor, reports invalid blocks,
// recovers them (re-creates from attributes), lets iu_style effects run, then saves.
let playwright;
try { playwright = require('playwright'); } catch (e) { playwright = require(require('child_process').execSync('npm root -g').toString().trim() + '/playwright'); }
const { chromium } = playwright;
const SITE = (process.env.SITE_URL || 'http://localhost:8080').replace(/\/$/, '');
const USER = process.env.WP_ADMIN_USER || 'devadmin';
const PASS = process.env.WP_ADMIN_PASS || 'devadmin';
(async () => {
  const ids = process.argv.slice(2);
  const dry = process.env.DRY === '1';
  const b = await chromium.launch();
  const ctx = await b.newContext({ viewport: { width: 1600, height: 1000 } });
  const p = await ctx.newPage();
  await p.goto(SITE + '/wp-login.php');
  await p.fill('#user_login', USER); await p.fill('#user_pass', PASS);
  await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
  for (const id of ids) {
    await p.goto(`${SITE}/wp-admin/post.php?post=${id}&action=edit`, { waitUntil: 'domcontentloaded' });
    await p.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 60000 });
    await p.evaluate(() => { try { wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false); } catch (e) {} });
    await p.waitForTimeout(2500);
    const res = await p.evaluate(async (dry) => {
      const sel = wp.data.select('core/block-editor');
      const invalid = [];
      const walk = (blocks) => blocks.forEach(bl => { if (!bl.isValid) invalid.push(bl.name + ' ' + JSON.stringify(bl.validationIssues ? bl.validationIssues.map(i => i.args && i.args[0]).slice(0,1) : '').slice(0, 300)); walk(bl.innerBlocks); });
      walk(sel.getBlocks());
      const unknown = [];
      const walk2 = (blocks) => blocks.forEach(bl => { if (bl.name === 'core/missing') unknown.push(bl.attributes.originalName); walk2(bl.innerBlocks); });
      walk2(sel.getBlocks());
      if (!dry) {
        const recreate = (bl) => wp.blocks.createBlock(bl.name, bl.attributes, bl.innerBlocks.map(recreate));
        const fix = (blocks) => blocks.forEach(bl => {
          if (!bl.isValid) wp.data.dispatch('core/block-editor').replaceBlock(bl.clientId, recreate(bl));
          else fix(bl.innerBlocks);
        });
        fix(sel.getBlocks());
      }
      return { invalid, unknown, count: sel.getGlobalBlockCount() };
    }, dry);
    let saved = false;
    if (!dry) {
      await p.waitForTimeout(2500);
      await p.evaluate(() => wp.data.dispatch('core/editor').savePost());
      await p.waitForFunction(() => !wp.data.select('core/editor').isSavingPost(), null, { timeout: 60000 });
      saved = true;
    }
    console.log(JSON.stringify({ id, ...res, saved }));
  }
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
