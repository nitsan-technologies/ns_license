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
  const version = item.version ? `v${item.version}` : '';
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

  const heroBg = view.querySelector('.js-product-detail-hero-bg');
  if (heroBg) {
    if (heroImage) {
      const safeUrl = String(heroImage).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
      heroBg.style.backgroundImage = `url("${safeUrl}")`;
      heroBg.classList.add('has-image');
    } else {
      heroBg.style.backgroundImage = '';
      heroBg.classList.remove('has-image');
    }
  }

  const badges = view.querySelector('.js-product-detail-badges');
  if (badges) {
    const parts = [];
    if (item.category) {
      parts.push(`<span class="ns-product-detail__badge ns-product-detail__badge--category">${escapeHtml(item.category)}</span>`);
    }
    if (item.badge) {
      parts.push(`<span class="ns-product-detail__badge ns-product-detail__badge--promo">${escapeHtml(item.badge)}</span>`);
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
    subtitle.textContent = [key, version].filter(Boolean).join('  |  ');
  }

  const heroStats = view.querySelector('.js-product-detail-hero-stats');
  if (heroStats) {
    const bits = [];
    if (item.rating) {
      bits.push(`<span class="ns-product-detail__stat"><span class="ns-product-detail__stars" aria-hidden="true">★★★★★</span> ${escapeHtml(String(item.rating))}</span>`);
    }
    const downloads = formatDownloads(item.downloads);
    if (downloads) {
      bits.push(`<span class="ns-product-detail__stat">${escapeHtml(downloads)} downloads</span>`);
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
      `<span class="ns-product-detail__keyword">${tagIcon}<span>${escapeHtml(String(entry))}</span></span>`
    )).join('');
  }
  setVisible(keywordsSection, keywords.length > 0);

  const featuresSection = view.querySelector('.js-product-detail-features-section');
  const featuresEl = view.querySelector('.js-product-detail-features');
  const features = Array.isArray(item.features) ? item.features : [];
  if (featuresEl) {
    featuresEl.innerHTML = features.map((entry) => (
      `<div class="ns-product-detail__feature"><span class="ns-product-detail__feature-icon" aria-hidden="true">✓</span><span>${escapeHtml(String(entry))}</span></div>`
    )).join('');
  }
  setVisible(featuresSection, features.length > 0);

  populateChangelog(view, item);
  populateFaq(view, item);
  populateActions(view, item, key, isFree, price);
  populateComposer(view, item);
  populateResources(view, item);
  populateMeta(view, item, key, version);
  populateDependencies(view, item);
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
    const body = changes.length
      ? `<ul class="mb-0">${changes.map((c) => `<li>${escapeHtml(String(c))}</li>`).join('')}</ul>`
      : '<p class="text-variant mb-0">—</p>';
    const itemEl = document.createElement('div');
    itemEl.className = 'accordion-item';
    itemEl.innerHTML = `
      <h3 class="accordion-header">
        <button class="accordion-button${open ? '' : ' collapsed'}" type="button" data-bs-toggle="collapse" data-bs-target="#${id}" aria-expanded="${open ? 'true' : 'false'}" aria-controls="${id}">
          ${escapeHtml(heading)}
        </button>
      </h3>
      <div id="${id}" class="accordion-collapse collapse${open ? ' show' : ''}" data-bs-parent="#product-detail-changelog">
        <div class="accordion-body">${body}</div>
      </div>`;
    container.appendChild(itemEl);
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
    const id = `pd-faq-${index}`;
    const itemEl = document.createElement('div');
    itemEl.className = 'accordion-item';
    itemEl.innerHTML = `
      <h3 class="accordion-header">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#${id}" aria-expanded="false" aria-controls="${id}">
          ${escapeHtml(entry.q || '')}
        </button>
      </h3>
      <div id="${id}" class="accordion-collapse collapse" data-bs-parent="#product-detail-faq">
        <div class="accordion-body">${escapeHtml(entry.a || '')}</div>
      </div>`;
    container.appendChild(itemEl);
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
  }

  if (item.liveDemoUrl) {
    const demo = document.createElement('a');
    demo.href = item.liveDemoUrl;
    demo.target = '_blank';
    demo.rel = 'noopener noreferrer';
    demo.className = 'btn btn-default';
    demo.textContent = view.dataset.labelDemo || 'Live Demo';
    actions.appendChild(demo);
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

  // Show list card data immediately, then enrich with full detail (tags, features, FAQ…).
  if (listItem) {
    populateView(view, listItem);
    toggleDetailMode(true);
  }

  loadFullProductDetail(extensionKey, listItem || {}).then((fullItem) => {
    if (!fullItem) {
      return;
    }
    populateView(view, fullItem);
    toggleDetailMode(true);
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
