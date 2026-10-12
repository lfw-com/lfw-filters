// Browser checks for LFW Filters against a local WordPress with the fixture
// (tests/sample-programs.php + tests/seed.php).
//
//   node wp-plugins/lfw-filters/tests/e2e.mjs [baseUrl] [screenshotDir]
//
// Checks: JavaScript on and off give the same results for the same URL,
// invalid parameters are dropped and never shown, the count is announced in a
// polite live region, focus after removing a chip, Escape closes a menu,
// target sizes and text sizes, and axe (WCAG 2.2 AA) at 1440 and 390.
import { createRequire } from "node:module";
import { mkdirSync } from "node:fs";
import path from "node:path";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");
const { AxeBuilder } = require("@axe-core/playwright");

const BASE = (process.argv[2] || "http://127.0.0.1:8099").replace(/\/$/, "");
const SHOTS = process.argv[3] || "";
if (SHOTS) mkdirSync(SHOTS, { recursive: true });

let pass = 0;
let fail = 0;
const axeLog = [];
function check(name, ok, detail = "") {
  if (ok) {
    pass++;
    console.log(`ok   ${name}`);
  } else {
    fail++;
    console.log(`FAIL ${name}${detail ? " :: " + detail : ""}`);
  }
}

async function titles(page) {
  return page.locator("#lfwf-results h3 a").allTextContents();
}
async function countText(page) {
  return ((await page.locator("#lfwf-count").textContent()) || "").trim();
}
async function waitCount(page, before) {
  await page.waitForFunction(
    (b) => {
      const el = globalThis.document.getElementById("lfwf-count");
      return el && el.textContent.trim() !== b && el.textContent.trim() !== "";
    },
    before,
    { timeout: 5000 },
  );
  await page.waitForFunction(() => !globalThis.document.querySelector("#lfwf-results[aria-busy]"), null, { timeout: 5000 });
}

async function axe(page, label) {
  // Theme chrome (Twenty Twenty-Five header and footer) is not the plugin's; the fixture runs on it.
  const res = await new AxeBuilder({ page }).exclude(".wp-site-blocks > header").exclude(".wp-site-blocks > footer").withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  axeLog.push({ label, violations: res.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target.join(" ")) })) });
  check(`axe 0 violations: ${label}`, res.violations.length === 0, res.violations.map((v) => `${v.id} (${v.nodes.map((n) => n.target.join(" ")).join(", ")})`).join("; "));
}

async function shot(page, name) {
  if (SHOTS) await page.screenshot({ path: path.join(SHOTS, name + ".png"), fullPage: true });
}

async function noJsResults(browser, url) {
  const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 1000 } });
  const page = await ctx.newPage();
  await page.goto(url);
  const out = { titles: await titles(page), count: await countText(page), groups: await page.locator(".lfwf-group-heading").allTextContents() };
  await ctx.close();
  return out;
}

const browser = await chromium.launch();
try {
  // ---------- Band (Dunmere) ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto(BASE + "/programs-band/");
    check("band: submit button hidden with JavaScript", await page.locator("[data-lfwf-submit]").isHidden());
    check("band: count is a polite live region", (await page.locator("#lfwf-count").getAttribute("aria-live")) === "polite");
    await axe(page, "band 1440 unfiltered");
    await shot(page, "band-1440-unfiltered");

    // Open a menu, pick two areas and one credential.
    let before = await countText(page);
    await page.locator("summary", { hasText: "Interest area" }).click();
    await shot(page, "band-1440-menu-open");
    await axe(page, "band 1440 menu open");
    await page.getByLabel(/^Health and nursing/).check();
    await waitCount(page, before);
    check("band: count updates in place", (await countText(page)) === "Showing 4 of 30 programs", await countText(page));
    check("band: URL updated with history", page.url().endsWith("/programs-band/?area=health"), page.url());
    check("band: focus stays on the checkbox", await page.evaluate(() => globalThis.document.activeElement.value === "health"));
    before = await countText(page);
    await page.getByLabel(/^Business/).check();
    await waitCount(page, before);
    await page.keyboard.press("Escape");
    check("band: Escape closes the menu", !(await page.locator("details.lfwf-drop").first().evaluate((d) => d.open)));
    check("band: focus returns to the summary", await page.evaluate(() => globalThis.document.activeElement.tagName === "SUMMARY"));
    before = await countText(page);
    await page.locator("summary", { hasText: "Degree level" }).click();
    await page.getByLabel(/^Bachelor's/).check();
    await waitCount(page, before);
    await page.keyboard.press("Escape");
    const jsUrl = page.url();
    const jsTitles = await titles(page);
    const jsCount = await countText(page);
    check("band: badge shows how many are selected", ((await page.locator('[data-lfwf-sync="sel-area"]').textContent()) || "").startsWith("2"));
    const nojs = await noJsResults(browser, jsUrl);
    check("band: JavaScript on and off give identical results", JSON.stringify(nojs.titles) === JSON.stringify(jsTitles) && nojs.count === jsCount, `${jsUrl} js=${jsTitles.join("|")} nojs=${nojs.titles.join("|")}`);
    check("band: filtered results are what the fixture says", jsTitles.join("|") === "Accounting|Business Administration|Economics|Kinesiology|Nursing|Nutrition Science|Public Health", jsTitles.join("|"));
    await axe(page, "band 1440 filtered");
    await shot(page, "band-1440-filtered");

    // Chip removal and focus.
    const chips = page.locator("#lfwf-active a.lfwf-chip");
    const nChips = await chips.count();
    before = await countText(page);
    await page.locator('#lfwf-active a.lfwf-chip[data-lfwf-remove="area:health"]').click();
    await waitCount(page, before);
    check("band: chip removal drops one chip", (await page.locator("#lfwf-active a.lfwf-chip").count()) === nChips - 1);
    check("band: focus moves to the next chip", await page.evaluate(() => globalThis.document.activeElement.classList.contains("lfwf-chip")));
    {
      const st = { health: await page.locator('input[name="area"][value="health"]').isChecked(), business: await page.locator('input[name="area"][value="business"]').isChecked(), url: page.url() };
      check("band: unchecks the matching control", !st.health && st.business, JSON.stringify(st));
    }

    // Back button.
    before = await countText(page);
    await page.goBack();
    await waitCount(page, before);
    check("band: back restores the previous view", (await titles(page)).join("|") === jsTitles.join("|"));
    check("band: back re-syncs controls", await page.locator('input[name="area"][value="business"]').isChecked());

    // Keyword (typed text stays only in the field).
    before = await countText(page);
    await page.locator("#lfwf-active [data-lfwf-clear]").click();
    await waitCount(page, before);
    check("band: Clear filters resets", (await countText(page)) === "30 programs" && page.url().endsWith("/programs-band/"), page.url());
    check("band: Clear filters moves focus to the keyword field", await page.evaluate(() => globalThis.document.activeElement.id === "lfwf-q"));
    before = await countText(page);
    await page.locator("#lfwf-q").fill("nurs");
    await waitCount(page, before);
    check("band: keyword filters", (await titles(page)).join("|") === "Nursing");
    check("band: keyword chip is generic", (await page.locator("#lfwf-active").textContent()).includes("Keyword search"));
    await ctx.close();
  }

  // ---------- No-JavaScript form submit ----------
  {
    const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto(BASE + "/programs-band/");
    check("no-JS: submit button visible", await page.locator("[data-lfwf-submit] button").isVisible());
    await page.locator("summary", { hasText: "Interest area" }).click();
    await page.getByLabel(/^Health and nursing/).check();
    await page.getByLabel(/^Business/).check();
    await page.locator("summary", { hasText: "Interest area" }).click();
    await page.locator("summary", { hasText: "Degree level" }).click();
    await page.getByLabel(/^Bachelor's/).check();
    await page.locator("[data-lfwf-submit] button").click();
    await page.waitForLoadState();
    check("no-JS: repeated parameters in the URL", /area=business&area=health&credential=bachelors/.test(page.url()), page.url());
    check("no-JS: same results as the JavaScript run", (await titles(page)).join("|") === "Accounting|Business Administration|Economics|Kinesiology|Nursing|Nutrition Science|Public Health");
    await page.locator('#lfwf-active a.lfwf-chip[data-lfwf-remove="area:health"]').click();
    await page.waitForLoadState();
    check("no-JS: chip link removes one value", !page.url().includes("area=health") && page.url().includes("area=business"), page.url());
    await ctx.close();
  }

  // ---------- Invalid parameters ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    const bad = BASE + "/programs-band/?area=health&area=zzbogus&campus=" + encodeURIComponent('"><b>zzhostile</b>') + "&internal_flag=secret&zzjunk=1";
    const res = await page.goto(bad);
    const html = await res.text();
    check("invalid: bogus values never in the response", !/zzbogus|zzhostile|zzjunk/.test(html));
    check("invalid: valid value kept", (await countText(page)) === "Showing 4 of 30 programs", await countText(page));
    check("invalid: one chip only", (await page.locator("#lfwf-active a.lfwf-chip").count()) === 1);
    check("invalid: private taxonomy ignored", !html.includes("Secret"));
    check("invalid: filtered view is noindex", /<meta name='robots' content='[^']*noindex/.test(html));
    const nojs = await noJsResults(browser, bad);
    check("invalid: JavaScript on and off agree", nojs.titles.join("|") === (await titles(page)).join("|"));
    await ctx.close();
  }

  // ---------- Panel (Calder) ----------
  {
    const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
    const page = await ctx.newPage();
    await page.goto(BASE + "/programs-panel/");
    await axe(page, "panel 1440 unfiltered");
    await shot(page, "panel-1440-unfiltered");
    let before = await countText(page);
    await page.locator(".lfwf-pill", { hasText: "Evening" }).click();
    await waitCount(page, before);
    before = await countText(page);
    await page.locator(".lfwf-pill", { hasText: "Riverton" }).click();
    await waitCount(page, before);
    const url = page.url();
    const js = { titles: await titles(page), groups: await page.locator(".lfwf-group-heading").allTextContents(), count: await countText(page) };
    const nojs = await noJsResults(browser, url);
    check("panel: JavaScript on and off give identical grouped results", JSON.stringify([js.titles, js.groups, js.count]) === JSON.stringify([nojs.titles, nojs.groups, nojs.count]), `${url} ${JSON.stringify(js)} vs ${JSON.stringify(nojs)}`);
    check("panel: groups follow filters", js.groups.length > 0 && js.titles.join("|") === "Accounting|Criminal Justice|Social Work", js.titles.join("|"));
    await axe(page, "panel 1440 filtered");
    await shot(page, "panel-1440-filtered");
    await page.goto(BASE + "/programs-panel/?area=agriculture&credential=credential");
    check("panel: empty state", (await page.locator(".lfwf-empty").count()) === 1 && (await countText(page)) === "No programs match");
    await axe(page, "panel 1440 empty");
    await ctx.close();
  }

  // ---------- Phone width ----------
  for (const which of ["band", "panel"]) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
    const page = await ctx.newPage();
    await page.goto(BASE + `/programs-${which}/`);
    if (which === "panel") {
      check("panel 390: folds into a Filters disclosure", !(await page.locator("[data-lfwf-panel]").evaluate((d) => d.open)));
      await page.locator(".lfwf-panel-summary").click();
    } else {
      await page.locator("summary", { hasText: "Interest area" }).click();
    }
    await axe(page, `${which} 390 controls open`);
    const before = await countText(page);
    await (which === "band" ? page.getByLabel(/^Health and nursing/).check() : page.locator(".lfwf-pill", { hasText: "Health and nursing" }).click());
    await waitCount(page, before);
    await axe(page, `${which} 390 filtered`);
    const overflow = await page.evaluate(() => globalThis.document.documentElement.scrollWidth > globalThis.innerWidth + 1);
    check(`${which} 390: no horizontal scroll`, !overflow);
    await shot(page, `${which}-390-filtered`);

    // Target and text sizes inside the plugin's markup.
    const small = await page.evaluate(() => {
      const out = [];
      const sel = ".lfwf summary, .lfwf .lfwf-pill, .lfwf .lfwf-check, .lfwf .lfwf-chip, .lfwf .lfwf-clear, .lfwf .lfwf-input, .lfwf .lfwf-select, .lfwf .lfwf-button";
      for (const el of globalThis.document.querySelectorAll(sel)) {
        const r = el.getBoundingClientRect();
        if (r.width && r.height && r.height < 44) out.push(`target ${el.className} ${Math.round(r.height)}px`);
      }
      for (const el of globalThis.document.querySelectorAll(".lfwf *")) {
        if (!el.childNodes.length || el.closest(".lfwf-sr")) continue;
        const hasText = Array.from(el.childNodes).some((n) => n.nodeType === 3 && n.textContent.trim());
        const r = el.getBoundingClientRect();
        if (hasText && r.width && parseFloat(globalThis.getComputedStyle(el).fontSize) < 14) out.push(`text ${el.className} ${globalThis.getComputedStyle(el).fontSize}`);
      }
      return out;
    });
    check(`${which} 390: targets at least 44px, text at least 14px`, small.length === 0, small.slice(0, 6).join("; "));
    await ctx.close();
  }
} finally {
  await browser.close();
}

if (SHOTS) {
  const { writeFileSync } = await import("node:fs");
  writeFileSync(path.join(SHOTS, "axe-results.json"), JSON.stringify(axeLog, null, 2));
}
console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
