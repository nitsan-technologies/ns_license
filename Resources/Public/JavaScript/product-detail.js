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
      const downloadIcon = getProductDetailIconHtml(view, 'stat-download');
      bits.push(
        `<span class="ns-product-detail__stat ns-product-detail__stat--downloads">`
        + (downloadIcon ? `<span class="ns-product-detail__stat-icon" aria-hidden="true">${downloadIcon}</span>` : '')
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
  populateRelated(view, item);
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
 * @param {{ id: string, title?: string, titleHtml?: string, bodyHtml: string, open?: boolean }} opts
 * @returns {HTMLElement}
 */
function createCorePanel(opts) {
  const { id, title = '', titleHtml = '', bodyHtml, open = false } = opts;
  const titleContent = titleHtml || escapeHtml(title);
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
          <div class="panel-title">${titleContent}</div>
          <span class="caret"></span>
        </button>
      </div>
    </h3>
    <div class="panel-collapse collapse${open ? ' show' : ''}" id="${id}" role="tabpanel">
      <div class="panel-body">${bodyHtml}</div>
    </div>`;
  return el;
}

const CHANGELOG_TYPES = new Set(['bugfix', 'feature', 'task', 'release']);

/** @type {Record<string, string>} */
const CHANGELOG_BADGE_CLASS = {
  feature: 'badge badge-success',
  task: 'badge badge-info',
  bugfix: 'badge badge-warning',
  release: 'badge badge-primary',
};

/**
 * Format release date like "31st Jul 2026" when parseable.
 * @param {unknown} raw
 * @returns {string}
 */
function formatChangelogDate(raw) {
  const s = String(raw ?? '').trim();
  if (!s) {
    return '';
  }
  if (/\d+(st|nd|rd|th)\b/i.test(s)) {
    return s;
  }
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) {
    return s;
  }
  const day = d.getDate();
  const j = day % 10;
  const k = day % 100;
  let suffix = 'th';
  if (j === 1 && k !== 11) {
    suffix = 'st';
  } else if (j === 2 && k !== 12) {
    suffix = 'nd';
  } else if (j === 3 && k !== 13) {
    suffix = 'rd';
  }
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return `${day}${suffix} ${months[d.getMonth()]} ${d.getFullYear()}`;
}

/**
 * Parse changelog line into type + text.
 * Supports **FEATURE**, FEATURE** (legacy stripped), and [FEATURE].
 * @param {unknown} line
 * @returns {{ type: string, text: string }}
 */
function parseChangelogChange(line) {
  const raw = String(line ?? '').trim();
  const patterns = [
    /^\*\*([A-Za-z]+)\*\*\s*(.*)$/,
    /^([A-Za-z]+)\*\*\s*(.*)$/,
    /^\[([A-Za-z]+)\]\s*(.*)$/,
  ];
  for (const re of patterns) {
    const match = raw.match(re);
    if (!match) {
      continue;
    }
    const type = match[1].toLowerCase();
    if (!CHANGELOG_TYPES.has(type)) {
      continue;
    }
    return { type, text: String(match[2] || '').trim() || raw };
  }
  return { type: '', text: raw };
}

/**
 * @param {{ type: string, text: string }} change
 * @returns {string}
 */
function renderChangelogChangeRow(change) {
  const badgeClass = CHANGELOG_BADGE_CLASS[change.type] || '';
  const badge = change.type && badgeClass
    ? `<span class="${badgeClass} ns-product-detail__change-badge">${escapeHtml(change.type.toUpperCase())}</span>`
    : '';
  const text = change.text
    ? `<span class="ns-product-detail__change-text">${escapeHtml(change.text)}</span>`
    : '';
  return `<li class="ns-product-detail__change-row">${badge}${text}</li>`;
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
  const clockTemplate = view.querySelector('.js-product-detail-icon-templates [data-icon="changelog-clock"]');
  const clockIcon = clockTemplate
    ? `<span class="ns-product-detail__changelog-icon" aria-hidden="true">${clockTemplate.innerHTML.trim()}</span>`
    : '';
  entries.forEach((entry, index) => {
    const id = `pd-changelog-${index}`;
    const open = index === 0;
    const version = String(entry.version || '').trim();
    const dateLabel = formatChangelogDate(entry.date);
    const latestBadge = index === 0
      ? `<span class="badge badge-warning ns-product-detail__latest-badge">${escapeHtml(latestLabel)}</span>`
      : '';
    const titleHtml = [
      clockIcon,
      version ? `<span class="ns-product-detail__changelog-version">${escapeHtml(version)}</span>` : '',
      latestBadge,
    ].filter(Boolean).join('');
    const changes = Array.isArray(entry.changes) ? entry.changes : [];
    const rows = changes.map((c) => renderChangelogChangeRow(parseChangelogChange(c))).join('');
    const dateHtml = dateLabel
      ? `<div class="ns-product-detail__changelog-date">${escapeHtml(dateLabel)}</div>`
      : '';
    const bodyHtml = changes.length
      ? `${dateHtml}<ul class="ns-product-detail__change-list list-unstyled mb-0">${rows}</ul>`
      : `${dateHtml}<p class="text-variant mb-0">—</p>`;
    container.appendChild(createCorePanel({
      id,
      titleHtml,
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
 * Frequently Bought Together rows from relatedProducts (slim catalog cards).
 * @param {HTMLElement} view
 * @param {object} item
 */
function populateRelated(view, item) {
  const section = view.querySelector('.js-product-detail-related-section');
  const container = view.querySelector('.js-product-detail-related');
  const entries = Array.isArray(item.relatedProducts) ? item.relatedProducts : [];
  if (!container) {
    return;
  }
  container.innerHTML = '';
  if (entries.length === 0) {
    setVisible(section, false);
    return;
  }

  const viewLabel = section?.dataset.labelView || 'View';
  const fallbackIcon = '<svg class="ns-product-detail__related-fallback-icon" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2.5 13.8 8h5.7l-4.6 3.4 1.8 5.5L12 13.7 7.3 16.9l1.8-5.5L4.5 8h5.7L12 2.5z"/></svg>';

  entries.forEach((entry) => {
    if (!entry || typeof entry !== 'object') {
      return;
    }
    const extensionKey = String(entry.extensionKey || '').trim();
    if (!extensionKey) {
      return;
    }
    const name = String(entry.name || extensionKey);
    const price = String(entry.price || '').trim();
    const badge = String(entry.badge || '').trim();
    const listImage = String(entry.listImage || '').trim();

    const row = document.createElement('div');
    row.className = 'ns-product-detail__related-row';
    row.setAttribute('role', 'listitem');

    const media = document.createElement('div');
    media.className = 'ns-product-detail__related-media';
    if (listImage) {
      const img = document.createElement('img');
      img.src = listImage;
      img.alt = '';
      img.loading = 'lazy';
      media.appendChild(img);
    } else {
      media.innerHTML = fallbackIcon;
    }

    const body = document.createElement('div');
    body.className = 'ns-product-detail__related-body';

    const titleRow = document.createElement('div');
    titleRow.className = 'ns-product-detail__related-title-row';
    const title = document.createElement('span');
    title.className = 'ns-product-detail__related-name';
    title.textContent = name;
    titleRow.appendChild(title);
    if (badge) {
      const badgeEl = document.createElement('span');
      badgeEl.className = 'badge badge-info ns-product-detail__related-badge';
      badgeEl.textContent = badge;
      titleRow.appendChild(badgeEl);
    }

    const keyEl = document.createElement('div');
    keyEl.className = 'ns-product-detail__related-key';
    keyEl.textContent = extensionKey;

    body.appendChild(titleRow);
    body.appendChild(keyEl);

    const priceEl = document.createElement('div');
    priceEl.className = 'ns-product-detail__related-price';
    priceEl.textContent = price;

    const viewBtn = document.createElement('button');
    viewBtn.type = 'button';
    viewBtn.className = 'btn btn-default btn-sm t3js-product-detail-trigger ns-product-detail__related-view';
    viewBtn.dataset.extensionKey = extensionKey;
    viewBtn.textContent = viewLabel;

    row.appendChild(media);
    row.appendChild(body);
    row.appendChild(priceEl);
    row.appendChild(viewBtn);
    container.appendChild(row);
  });

  setVisible(section, container.children.length > 0);
}

/**
 * @param {HTMLElement} view
 * @param {HTMLElement} el
 * @param {{ label: string, leadingIcon?: string, external?: boolean }} opts
 */
function setProductDetailCtaContent(view, el, opts) {
  const leading = opts.leadingIcon ? getProductDetailIconHtml(view, opts.leadingIcon) : '';
  const external = opts.external ? getProductDetailIconHtml(view, 'resource-external') : '';
  el.innerHTML = [
    leading ? `<span class="ns-product-detail__cta-icon" aria-hidden="true">${leading}</span>` : '',
    `<span class="ns-product-detail__cta-label">${escapeHtml(opts.label)}</span>`,
    external ? `<span class="ns-product-detail__cta-external" aria-hidden="true">${external}</span>` : '',
  ].filter(Boolean).join('');
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
    setProductDetailCtaContent(view, buyBtn, { label: buyText, leadingIcon: 'cta-cart' });
    actions.appendChild(buyBtn);

    const trialBtn = document.createElement('button');
    trialBtn.type = 'button';
    trialBtn.className = 'btn btn-default t3js-get-license-trigger';
    trialBtn.dataset.extensionKey = key;
    trialBtn.dataset.glMode = 'trial';
    setProductDetailCtaContent(view, trialBtn, {
      label: view.dataset.labelTrial || 'Free Trial',
      leadingIcon: 'cta-trial',
    });
    actions.appendChild(trialBtn);
  } else if (isFree) {
    const knowMoreUrl = item.knowMoreUrl || item.productUrl || '';
    if (knowMoreUrl) {
      const knowMore = document.createElement('a');
      knowMore.href = knowMoreUrl;
      knowMore.target = '_blank';
      knowMore.rel = 'noopener noreferrer';
      knowMore.className = 'btn btn-primary';
      setProductDetailCtaContent(view, knowMore, {
        label: view.dataset.labelKnowMore || 'Know More',
        external: true,
      });
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
      setProductDetailCtaContent(view, fe, {
        label: view.dataset.labelDemoFrontend || 'Frontend Demo',
        external: true,
      });
      actions.appendChild(fe);

      const be = document.createElement('a');
      be.href = backendUrl;
      be.target = '_blank';
      be.rel = 'noopener noreferrer';
      be.className = 'btn btn-default';
      setProductDetailCtaContent(view, be, {
        label: view.dataset.labelDemoBackend || 'Backend Demo',
        external: true,
      });
      actions.appendChild(be);
    } else {
      const demoUrl = frontendUrl || backendUrl;
      const demo = document.createElement('a');
      demo.href = demoUrl;
      demo.target = '_blank';
      demo.rel = 'noopener noreferrer';
      demo.className = 'btn btn-default';
      setProductDetailCtaContent(view, demo, {
        label: backendUrl && !frontendUrl
          ? (view.dataset.labelDemoBackend || 'Backend Demo')
          : (view.dataset.labelDemo || 'Live Demo'),
        external: true,
      });
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
/**
 * Clone a Fluid-rendered icon from product-detail templates.
 * @param {HTMLElement} view
 * @param {string} name
 * @returns {string}
 */
function getProductDetailIconHtml(view, name) {
  const tpl = view.querySelector(`.js-product-detail-icon-templates [data-icon="${name}"]`);
  return tpl ? tpl.innerHTML.trim() : '';
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
  const externalIcon = getProductDetailIconHtml(view, 'resource-external');
  const links = [
    {
      href: String(item.documentationUrl || item.documentationLink || item.documentation_link || '').trim(),
      label: view.dataset.labelDocs || 'Extension Manual',
      icon: 'resource-docs',
    },
    {
      href: String(item.productUrl || item.knowMoreUrl || '').trim(),
      label: view.dataset.labelProductPage || 'T3Planet Page',
      icon: 'resource-product',
    },
    {
      href: String(
        item.scheduleCallUrl
        || item.bookCallUrl
        || item.scheduleUrl
        || view.dataset.scheduleCallUrl
        || ''
      ).trim(),
      label: view.dataset.labelScheduleCall || 'Schedule a Call',
      icon: 'resource-schedule',
    },
  ].filter((link) => link.href);

  links.forEach((link) => {
    const leadingIcon = getProductDetailIconHtml(view, link.icon);
    const li = document.createElement('li');
    li.className = 'ns-product-detail__resource-item';
    li.innerHTML = `<a class="ns-product-detail__resource-link" href="${escapeHtml(link.href)}" target="_blank" rel="noopener noreferrer">`
      + `<span class="ns-product-detail__resource-leading" aria-hidden="true">${leadingIcon}</span>`
      + `<span class="ns-product-detail__resource-label">${escapeHtml(link.label)}</span>`
      + `<span class="ns-product-detail__resource-external" aria-hidden="true">${externalIcon}</span>`
      + `</a>`;
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
    [view.dataset.labelDownloads || 'Downloads', formatDownloads(item.downloads)],
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
