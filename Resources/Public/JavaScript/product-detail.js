/**
 * Module: @nitsan/ns-license/product-detail
 * Full-page product detail view for catalog tabs.
 */

import AjaxRequest from '@typo3/core/ajax/ajax-request.js';

const VIEW_ID = 'product-detail-view';
const LIST_SELECTOR = '#license-tab-content';
const HEADER_SELECTOR = '.ns-license-tab-page-header';

/** @type {Record<string, object>} */
const detailCache = {};

/**
 * @param {boolean} show
 */
function setModuleLoader(show) {
  const loader = document.getElementById('nsLicenseLoader');
  if (!loader) {
    return;
  }
  loader.style.display = show ? '' : 'none';
  loader.setAttribute('aria-hidden', show ? 'false' : 'true');
}

/**
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text ?? '';
  return div.innerHTML;
}

/**
 * @param {number|string|null|undefined} ts
 * @returns {string}
 */
function formatDate(ts) {
  const n = Number(ts);
  if (!n || Number.isNaN(n)) {
    return '';
  }
  try {
    return new Date(n * 1000).toLocaleDateString(undefined, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch (e) {
    return '';
  }
}

/**
 * @param {unknown} value
 * @returns {string}
 */
function formatDownloads(value) {
  if (value === null || value === undefined || value === '') {
    return '';
  }
  const n = Number(value);
  if (!Number.isNaN(n) && n >= 1000) {
    const k = n / 1000;
    return (k % 1 === 0 ? k.toFixed(0) : k.toFixed(1)) + 'k';
  }
  return String(value);
}

/**
 * Normalize compatibility label (e.g. "TYPO3 v12 to v14" → "v12 to v14").
 * @param {unknown} version
 * @returns {string}
 */
function formatVersionSupport(version) {
  let v = String(version ?? '').trim();
  if (!v) {
    return '';
  }
  v = v.replace(/\bv?TYPO3\b\s*/gi, '').replace(/\s+/g, ' ').trim();
  if (!v) {
    return '';
  }
  v = v.replace(/\b(?!v)(\d+)/gi, 'v$1');
  return v;
}

/**
 * Exact fractional star fill (e.g. 4.6 → 92% of the 5-star row).
 * @param {unknown} rating
 * @returns {{ html: string, label: string }|null}
 */
function renderRatingStars(rating) {
  const num = Number.parseFloat(String(rating ?? '').replace(',', '.'));
  if (!Number.isFinite(num) || num <= 0) {
    return null;
  }
  const clamped = Math.min(5, Math.max(0, num));
  const pct = (clamped / 5) * 100;
  const label = String(rating).trim() || String(clamped);
  const html = `<span class="ns-product-detail__stars" style="--ns-pd-star-fill: ${pct}%;" aria-hidden="true">`
    + `<span class="ns-product-detail__stars-base">★★★★★</span>`
    + `<span class="ns-product-detail__stars-fill">★★★★★</span>`
    + `</span>`;
  return { html, label };
}

/**
 * @param {HTMLElement} el
 * @param {boolean} show
 */
function setVisible(el, show) {
  if (!el) {
    return;
  }
  el.classList.toggle('d-none', !show);
}

/**
 * @param {string} tab
 * @returns {string}
 */
function sectionLabel(tab) {
  const map = {
    'ai-universe': 'AI Universe',
    extensions: 'Extensions',
    templates: 'Templates',
  };
  return map[tab] || '';
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateView(view, item) {
  const name = item.name || '';
  const key = item.extensionKey || '';
  const version = formatVersionSupport(item.version);
  const price = item.price || '';
  const isFree = !!(item.isFree || price === 'Free');
  const heroImage = item.detailImage || item.listImage || '';

  const crumbShop = view.querySelector('.js-product-detail-crumb-shop');
  const crumbSection = view.querySelector('.js-product-detail-crumb-section');
  const crumbSectionSep = view.querySelector('.js-product-detail-crumb-section-sep');
  const crumbName = view.querySelector('.js-product-detail-crumb-name');
  if (crumbShop) {
    crumbShop.textContent = view.dataset.labelShop || 'T3Planet Shop';
  }
  const section = sectionLabel(item.catalogSection || '');
  if (crumbSection) {
    crumbSection.textContent = section;
  }
  setVisible(crumbSectionSep, !!section);
  setVisible(crumbSection, !!section);
  if (crumbName) {
    crumbName.textContent = name;
  }

  const hero = view.querySelector('.js-product-detail-hero');
  const heroBg = view.querySelector('.js-product-detail-hero-bg');
  if (hero) {
    hero.classList.toggle('has-image', !!heroImage);
  }
  if (heroBg) {
    if (heroImage) {
      heroBg.style.backgroundImage = `url(${JSON.stringify(heroImage)})`;
    } else {
      heroBg.style.backgroundImage = '';
    }
  }

  const badges = view.querySelector('.js-product-detail-badges');
  if (badges) {
    const parts = [];
    if (item.category) {
      parts.push(`<span class="badge badge-default">${escapeHtml(item.category)}</span>`);
    }
    if (item.badge) {
      parts.push(`<span class="badge badge-warning">${escapeHtml(item.badge)}</span>`);
    }
    badges.innerHTML = parts.join('');
    setVisible(badges, parts.length > 0);
  }

  const title = view.querySelector('.js-product-detail-title');
  if (title) {
    title.textContent = name;
  }

  const subtitle = view.querySelector('.js-product-detail-subtitle');
  if (subtitle) {
    subtitle.textContent = [key, version].filter(Boolean).join(' · ');
  }

  const heroStats = view.querySelector('.js-product-detail-hero-stats');
  if (heroStats) {
    const bits = [];
    if (item.rating) {
      const stars = renderRatingStars(item.rating);
      if (stars) {
        bits.push(
          `<span class="ns-product-detail__stat ns-product-detail__stat--rating">`
          + stars.html
          + `<strong class="ns-product-detail__stat-value">${escapeHtml(stars.label)}</strong>`
          + `</span>`
        );
      }
    }
    const downloads = formatDownloads(item.downloads);
    if (downloads) {
      const downloadsLabel = view.dataset.labelDownloads || 'Downloads';
      bits.push(
        `<span class="ns-product-detail__stat ns-product-detail__stat--downloads">`
        + `<strong class="ns-product-detail__stat-value">${escapeHtml(downloads)}</strong>`
        + `<span class="ns-product-detail__stat-label">${escapeHtml(downloadsLabel)}</span>`
        + `</span>`
      );
    }
    heroStats.innerHTML = bits.join('');
    setVisible(heroStats, bits.length > 0);
  }

  const longDescription = view.querySelector('.js-product-detail-long-description');
  const overviewSection = view.querySelector('.js-product-detail-overview-section');
  const overviewText = item.longDescription || item.description || '';
  if (longDescription) {
    longDescription.textContent = overviewText;
  }
  setVisible(overviewSection, !!overviewText);

  const keywordsSection = view.querySelector('.js-product-detail-keywords-section');
  const keywordsEl = view.querySelector('.js-product-detail-keywords');
  const keywords = Array.isArray(item.keywords)
    ? item.keywords
    : (Array.isArray(item.tags) ? item.tags : []);
  if (keywordsEl) {
    const tagIcon = '<svg class="ns-product-detail__keyword-icon" width="12" height="12" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M2 2h5.5L14 8.5 8.5 14 2 7.5V2zm2.5 2a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>';
    keywordsEl.innerHTML = keywords.map((entry) => (
      `<span class="badge badge-default">${tagIcon}<span>${escapeHtml(String(entry))}</span></span>`
    )).join('');
  }
  setVisible(keywordsSection, keywords.length > 0);

  const featuresSection = view.querySelector('.js-product-detail-features-section');
  const featuresEl = view.querySelector('.js-product-detail-features');
  const features = Array.isArray(item.features) ? item.features : [];
  if (featuresEl) {
    const checkIcon = '<span class="ns-product-detail__feature-icon" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 16 16" focusable="false"><path fill="currentColor" d="M13.78 4.22a.75.75 0 0 1 0 1.06l-6.25 6.25a.75.75 0 0 1-1.06 0L2.22 7.28a.75.75 0 0 1 1.06-1.06L7 9.94l5.72-5.72a.75.75 0 0 1 1.06 0z"/></svg></span>';
    featuresEl.innerHTML = features.map((entry) => (
      `<li class="ns-product-detail__feature">${checkIcon}<span>${escapeHtml(String(entry))}</span></li>`
    )).join('');
  }
  setVisible(featuresSection, features.length > 0);

  populateExternalNav(view, item);
  populateChangelog(view, item);
  populateFaq(view, item);
  populateActions(view, item, key, isFree, price);
  populateComposer(view, item);
  populateResources(view, item);
  populateMeta(view, item, key, version);
  populateDependencies(view, item);
}

/**
 * Meta-bar Features / Reviews / References → external product-page links.
 * Prefers API fields (featuresUrl / reviewsUrl / referencesUrl); falls back to productUrl.
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateExternalNav(view, item) {
  const base = String(item.productUrl || item.knowMoreUrl || '').trim();
  const sectionUrls = (item.sectionUrls && typeof item.sectionUrls === 'object') ? item.sectionUrls : {};
  const navLinks = (item.navLinks && typeof item.navLinks === 'object') ? item.navLinks : {};
  const byKey = {
    features: String(item.featuresUrl || sectionUrls.features || navLinks.features || '').trim(),
    reviews: String(item.reviewsUrl || sectionUrls.reviews || navLinks.reviews || '').trim(),
    references: String(item.referencesUrl || sectionUrls.references || navLinks.references || '').trim(),
  };

  view.querySelectorAll('.js-product-detail-ext-link').forEach((link) => {
    const key = String(link.dataset.extKey || '').trim();
    const url = byKey[key] || base;
    if (!url) {
      setVisible(link, false);
      link.removeAttribute('href');
      return;
    }
    link.href = url;
    setVisible(link, true);
  });
}

/**
 * Build a TYPO3 core collapsible panel (Styleguide Panels pattern).
 * @param {{ id: string, title: string, bodyHtml: string, open?: boolean }} opts
 * @returns {HTMLElement}
 */
function createCorePanel(opts) {
  const { id, title, bodyHtml, open = false } = opts;
  const el = document.createElement('div');
  el.className = 'panel panel-default';
  el.innerHTML = `
    <h3 class="panel-heading" role="tab">
      <div class="panel-heading-row">
        <button
          class="panel-button${open ? '' : ' collapsed'}"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#${id}"
          aria-expanded="${open ? 'true' : 'false'}"
          aria-controls="${id}"
        >
          <div class="panel-title">${escapeHtml(title)}</div>
          <span class="caret"></span>
        </button>
      </div>
    </h3>
    <div class="panel-collapse collapse${open ? ' show' : ''}" id="${id}" role="tabpanel">
      <div class="panel-body">${bodyHtml}</div>
    </div>`;
  return el;
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateChangelog(view, item) {
  const section = view.querySelector('.js-product-detail-changelog-section');
  const container = view.querySelector('.js-product-detail-changelog');
  const entries = Array.isArray(item.changelog) ? item.changelog : [];
  if (!container) {
    return;
  }
  container.innerHTML = '';
  if (entries.length === 0) {
    setVisible(section, false);
    return;
  }
  const latestLabel = view.dataset.labelLatest || 'Latest';
  entries.forEach((entry, index) => {
    const id = `pd-changelog-${index}`;
    const open = index === 0;
    const heading = [entry.version, index === 0 ? `(${latestLabel})` : '', entry.date]
      .filter(Boolean)
      .join(' ');
    const changes = Array.isArray(entry.changes) ? entry.changes : [];
    const bodyHtml = changes.length
      ? `<ul class="mb-0">${changes.map((c) => `<li>${escapeHtml(String(c))}</li>`).join('')}</ul>`
      : '<p class="text-variant mb-0">—</p>';
    container.appendChild(createCorePanel({
      id,
      title: heading,
      bodyHtml,
      open,
    }));
  });
  setVisible(section, true);
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateFaq(view, item) {
  const section = view.querySelector('.js-product-detail-faq-section');
  const container = view.querySelector('.js-product-detail-faq');
  const entries = Array.isArray(item.faq) ? item.faq : [];
  if (!container) {
    return;
  }
  container.innerHTML = '';
  if (entries.length === 0) {
    setVisible(section, false);
    return;
  }
  entries.forEach((entry, index) => {
    container.appendChild(createCorePanel({
      id: `pd-faq-${index}`,
      title: String(entry.q || ''),
      bodyHtml: `<p class="mb-0">${escapeHtml(entry.a || '')}</p>`,
      open: false,
    }));
  });
  setVisible(section, true);
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 * @param {string} key
 * @param {boolean} isFree
 * @param {string} price
 */
function populateActions(view, item, key, isFree, price) {
  const actions = view.querySelector('.js-product-detail-actions');
  if (!actions) {
    return;
  }
  actions.innerHTML = '';

  if (!isFree && key) {
    const buyLabel = view.dataset.labelBuy || 'Buy Now';
    const buyText = price ? `${buyLabel} — ${price}` : buyLabel;
    const buyBtn = document.createElement('button');
    buyBtn.type = 'button';
    buyBtn.className = 'btn btn-primary t3js-get-license-trigger';
    buyBtn.dataset.extensionKey = key;
    buyBtn.dataset.glMode = 'buy';
    buyBtn.textContent = buyText;
    actions.appendChild(buyBtn);

    const trialBtn = document.createElement('button');
    trialBtn.type = 'button';
    trialBtn.className = 'btn btn-default t3js-get-license-trigger';
    trialBtn.dataset.extensionKey = key;
    trialBtn.dataset.glMode = 'trial';
    trialBtn.textContent = view.dataset.labelTrial || 'Free Trial';
    actions.appendChild(trialBtn);
  } else if (isFree) {
    const knowMoreUrl = item.knowMoreUrl || item.productUrl || '';
    if (knowMoreUrl) {
      const knowMore = document.createElement('a');
      knowMore.href = knowMoreUrl;
      knowMore.target = '_blank';
      knowMore.rel = 'noopener noreferrer';
      knowMore.className = 'btn btn-primary';
      knowMore.textContent = view.dataset.labelKnowMore || 'Know More';
      actions.appendChild(knowMore);
    }
  }

  if (item.liveDemoUrl || item.frontendDemoUrl || item.backendDemoUrl) {
    const frontendUrl = item.frontendDemoUrl || item.liveDemoUrl || '';
    const backendUrl = item.backendDemoUrl || '';
    if (frontendUrl && backendUrl) {
      const fe = document.createElement('a');
      fe.href = frontendUrl;
      fe.target = '_blank';
      fe.rel = 'noopener noreferrer';
      fe.className = 'btn btn-default';
      fe.textContent = view.dataset.labelDemoFrontend || 'Frontend Demo';
      actions.appendChild(fe);

      const be = document.createElement('a');
      be.href = backendUrl;
      be.target = '_blank';
      be.rel = 'noopener noreferrer';
      be.className = 'btn btn-default';
      be.textContent = view.dataset.labelDemoBackend || 'Backend Demo';
      actions.appendChild(be);
    } else {
      const demoUrl = frontendUrl || backendUrl;
      const demo = document.createElement('a');
      demo.href = demoUrl;
      demo.target = '_blank';
      demo.rel = 'noopener noreferrer';
      demo.className = 'btn btn-default';
      demo.textContent = backendUrl && !frontendUrl
        ? (view.dataset.labelDemoBackend || 'Backend Demo')
        : (view.dataset.labelDemo || 'Live Demo');
      actions.appendChild(demo);
    }
  }

  setVisible(view.querySelector('.js-product-detail-cta-card'), actions.children.length > 0);
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateComposer(view, item) {
  const section = view.querySelector('.js-product-detail-composer-section');
  const code = view.querySelector('.js-product-detail-composer');
  const cmd = (item.composerCommand || '').trim();
  if (code) {
    code.textContent = cmd;
  }
  setVisible(section, !!cmd);
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateResources(view, item) {
  const section = view.querySelector('.js-product-detail-resources-section');
  const list = view.querySelector('.js-product-detail-resources');
  if (!list) {
    return;
  }
  list.innerHTML = '';
  const links = [];
  const docs = item.documentationUrl || item.documentation_link;
  if (docs) {
    links.push({ href: docs, label: view.dataset.labelDocs || 'Extension Manual' });
  }
  const product = item.productUrl || item.knowMoreUrl;
  if (product) {
    links.push({ href: product, label: view.dataset.labelProductPage || 'T3Planet Page' });
  }
  links.forEach((link) => {
    const li = document.createElement('li');
    li.innerHTML = `<a href="${escapeHtml(link.href)}" target="_blank" rel="noopener noreferrer">${escapeHtml(link.label)}</a>`;
    list.appendChild(li);
  });
  setVisible(section, links.length > 0);
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 * @param {string} key
 * @param {string} version
 */
function populateMeta(view, item, key, version) {
  const meta = view.querySelector('.js-product-detail-meta');
  if (!meta) {
    return;
  }
  const rows = [
    [view.dataset.labelAuthor || 'Author', item.author || 'Team T3Planet'],
    [view.dataset.labelCompany || 'Company', item.company || 'T3Planet'],
    [view.dataset.labelLastUpdate || 'Last Update', formatDate(item.lastUpdate)],
    [view.dataset.labelFirstUpload || 'First Upload', formatDate(item.firstUpload)],
    [view.dataset.labelDownloads || 'Downloads', item.downloads ? String(item.downloads) : ''],
    [view.dataset.labelCategory || 'Category', item.category || ''],
    [view.dataset.labelExtensionKey || 'Extension Key', key],
    [view.dataset.labelVersion || 'Version', version],
  ].filter(([, value]) => value !== '' && value != null);

  meta.innerHTML = rows.map(([label, value]) => (
    `<div class="ns-product-detail__meta-row"><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(String(value))}</dd></div>`
  )).join('');
}

/**
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateDependencies(view, item) {
  const section = view.querySelector('.js-product-detail-dependencies-section');
  const list = view.querySelector('.js-product-detail-dependencies');
  let deps = Array.isArray(item.dependencies) ? item.dependencies : [];
  // Support map form { "pkg": "^12" } if ever present client-side.
  if (!Array.isArray(item.dependencies) && item.dependencies && typeof item.dependencies === 'object') {
    deps = Object.entries(item.dependencies).map(([key, version]) => ({ key, version }));
  }
  if (!list) {
    return;
  }
  list.innerHTML = '';
  deps.forEach((dep) => {
    if (typeof dep === 'string') {
      const li = document.createElement('li');
      li.innerHTML = `<code>${escapeHtml(dep)}</code>`;
      list.appendChild(li);
      return;
    }
    const key = dep.key || dep.name || '';
    const ver = dep.version || '';
    if (!key && !ver) {
      return;
    }
    const li = document.createElement('li');
    li.innerHTML = `<code>${escapeHtml(key)}</code><span class="text-variant">${escapeHtml(ver)}</span>`;
    list.appendChild(li);
  });
  setVisible(section, list.children.length > 0);
}

/**
 * @param {boolean} show
 */
function toggleDetailMode(show) {
  const view = document.getElementById(VIEW_ID);
  const list = document.querySelector(LIST_SELECTOR);
  const header = document.querySelector(HEADER_SELECTOR);
  if (!view) {
    return;
  }
  if (show) {
    view.classList.remove('d-none');
    view.removeAttribute('hidden');
    view.setAttribute('aria-hidden', 'false');
    if (list) {
      list.classList.add('d-none');
    }
    if (header) {
      header.classList.add('d-none');
    }
    view.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } else {
    view.classList.add('d-none');
    view.setAttribute('hidden', 'hidden');
    view.setAttribute('aria-hidden', 'true');
    if (list) {
      list.classList.remove('d-none');
    }
    if (header) {
      header.classList.remove('d-none');
    }
  }
}

document.addEventListener('click', (e) => {
  const back = e.target.closest('.t3js-product-detail-back');
  if (back) {
    e.preventDefault();
    toggleDetailMode(false);
    return;
  }

  // Leaving detail when switching module tabs.
  if (e.target.closest('.ns-license-nav-tabs .nav-link, .t3js-catalog-tab, .t3js-services-tab, #my-extensions-tab, #services-tab')) {
    const view = document.getElementById(VIEW_ID);
    if (view && !view.classList.contains('d-none')) {
      toggleDetailMode(false);
    }
  }

  const copyBtn = e.target.closest('.t3js-product-detail-copy-composer');
  if (copyBtn) {
    e.preventDefault();
    const view = document.getElementById(VIEW_ID);
    const code = view?.querySelector('.js-product-detail-composer');
    const text = code?.textContent || '';
    if (!text) {
      return;
    }
    const done = () => {
      const prev = copyBtn.getAttribute('title') || '';
      copyBtn.setAttribute('title', view?.dataset.labelCopied || 'Copied');
      setTimeout(() => copyBtn.setAttribute('title', prev), 1500);
    };
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(text).then(done).catch(() => {});
    } else {
      done();
    }
    return;
  }

  const trigger = e.target.closest('.t3js-product-detail-trigger');
  if (!trigger) {
    return;
  }
  e.preventDefault();

  const view = document.getElementById(VIEW_ID);
  if (!view) {
    return;
  }

  const extensionKey = trigger.dataset.extensionKey || '';
  const pane = trigger.closest('.tab-pane');
  const script = pane?.querySelector('.catalog-items-json')
    || document.querySelector('.tab-pane.active .catalog-items-json');
  let items = {};
  try {
    items = JSON.parse(script?.textContent || '{}');
  } catch (err) {
    console.error(err);
  }

  const listItem = items[extensionKey];
  if (!listItem && !extensionKey) {
    return;
  }

  // Loader first, then content after detail data is ready.
  setModuleLoader(true);
  const list = document.querySelector(LIST_SELECTOR);
  const header = document.querySelector(HEADER_SELECTOR);
  if (list) {
    list.classList.add('d-none');
  }
  if (header) {
    header.classList.add('d-none');
  }
  // Keep detail hidden until populated.
  view.classList.add('d-none');
  view.setAttribute('hidden', 'hidden');
  view.setAttribute('aria-hidden', 'true');

  loadFullProductDetail(extensionKey, listItem || {})
    .then((fullItem) => {
      const item = fullItem || listItem;
      if (!item) {
        if (list) {
          list.classList.remove('d-none');
        }
        if (header) {
          header.classList.remove('d-none');
        }
        return;
      }
      populateView(view, item);
      toggleDetailMode(true);
    })
    .finally(() => {
      setModuleLoader(false);
    });
});

/**
 * @param {string} extensionKey
 * @param {object} fallback
 * @returns {Promise<object|null>}
 */
function loadFullProductDetail(extensionKey, fallback) {
  if (!extensionKey) {
    return Promise.resolve(fallback && Object.keys(fallback).length ? fallback : null);
  }
  if (detailCache[extensionKey]) {
    return Promise.resolve(detailCache[extensionKey]);
  }

  const ajaxUrl = TYPO3?.settings?.ajaxUrls?.catalog_product_detail;
  if (!ajaxUrl) {
    return Promise.resolve(fallback && Object.keys(fallback).length ? fallback : null);
  }

  return new AjaxRequest(ajaxUrl)
    .withQueryArguments({ extensionKey })
    .get()
    .then((response) => response.resolve())
    .then((payload) => {
      if (payload?.success && payload.item && typeof payload.item === 'object') {
        // Merge so list-only fields (e.g. catalogSection) are preserved when API omits them.
        const merged = { ...fallback, ...payload.item };
        detailCache[extensionKey] = merged;
        return merged;
      }
      return fallback && Object.keys(fallback).length ? fallback : null;
    })
    .catch((err) => {
      console.error(err);
      return fallback && Object.keys(fallback).length ? fallback : null;
    });
}
