/**
 * Module: @nitsan/ns-license/product-detail
 * Product detail modal for catalog tabs.
 */

const MODAL_ID = 'product-detail-modal';

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
 * @param {HTMLElement} modal
 * @returns {Record<string, object>}
 */
function getCatalogItemsMap(modal) {
  const trigger = modal.dataset.detailTrigger;
  if (!trigger) {
    return {};
  }
  const pane = document.querySelector(trigger.closest('.tab-pane') ? trigger : null);
  const activePane = document.querySelector('.tab-pane.active');
  const scope = activePane || document;
  const script = scope.querySelector('.catalog-items-json');
  if (!script) {
    return {};
  }
  try {
    return JSON.parse(script.textContent || '{}');
  } catch (e) {
    console.error('Invalid catalog JSON', e);
    return {};
  }
}

/**
 * @param {HTMLElement} modal
 * @param {object} item
 */
function populateModal(modal, item) {
  const title = modal.querySelector('.js-product-detail-title');
  const subtitle = modal.querySelector('.js-product-detail-subtitle');
  const description = modal.querySelector('.js-product-detail-description');
  const longDescription = modal.querySelector('.js-product-detail-long-description');
  const features = modal.querySelector('.js-product-detail-features');
  const changelog = modal.querySelector('.js-product-detail-changelog');
  const faq = modal.querySelector('.js-product-detail-faq');
  const meta = modal.querySelector('.js-product-detail-meta');
  const dependencies = modal.querySelector('.js-product-detail-dependencies');
  const actions = modal.querySelector('.js-product-detail-actions');

  const name = item.name || '';
  const key = item.extensionKey || '';
  const version = item.version ? `v${item.version}` : '';
  const price = item.price || '';

  if (title) title.textContent = name;
  if (subtitle) subtitle.textContent = [key, version, price].filter(Boolean).join(' · ');
  if (description) description.textContent = item.description || '';
  if (longDescription) {
    longDescription.textContent = item.longDescription || item.description || '';
  }

  if (features) {
    features.innerHTML = '';
    const list = Array.isArray(item.features) ? item.features : [];
    if (list.length === 0) {
      features.innerHTML = '<li class="text-variant">—</li>';
    } else {
      list.forEach((entry) => {
        const li = document.createElement('li');
        li.textContent = String(entry);
        features.appendChild(li);
      });
    }
  }

  if (changelog) {
    changelog.innerHTML = '';
    const entries = Array.isArray(item.changelog) ? item.changelog : [];
    if (entries.length === 0) {
      changelog.innerHTML = '<p class="text-variant mb-0">—</p>';
    } else {
      entries.forEach((entry) => {
        const block = document.createElement('div');
        block.className = 'mb-2';
        const heading = document.createElement('strong');
        heading.textContent = [entry.version, entry.date].filter(Boolean).join(' · ');
        block.appendChild(heading);
        const changes = Array.isArray(entry.changes) ? entry.changes : [];
        if (changes.length) {
          const ul = document.createElement('ul');
          ul.className = 'mb-0';
          changes.forEach((change) => {
            const li = document.createElement('li');
            li.textContent = String(change);
            ul.appendChild(li);
          });
          block.appendChild(ul);
        }
        changelog.appendChild(block);
      });
    }
  }

  if (faq) {
    faq.innerHTML = '';
    const entries = Array.isArray(item.faq) ? item.faq : [];
    if (entries.length === 0) {
      faq.innerHTML = '<p class="text-variant mb-0">—</p>';
    } else {
      entries.forEach((entry) => {
        const block = document.createElement('div');
        block.className = 'mb-2';
        block.innerHTML = `<strong>${escapeHtml(entry.q || '')}</strong><p class="mb-0">${escapeHtml(entry.a || '')}</p>`;
        faq.appendChild(block);
      });
    }
  }

  if (meta) {
    const rows = [
      ['Author', item.author || 'Team T3Planet'],
      ['Company', item.company || 'T3Planet'],
      ['Category', item.category || '—'],
      ['Extension Key', key || '—'],
      ['Version', version || '—'],
      ['Downloads', item.downloads || '—'],
      ['Rating', item.rating || '—'],
    ];
    meta.innerHTML = rows.map(([label, value]) => (
      `<dt class="col-sm-4">${escapeHtml(label)}</dt><dd class="col-sm-8">${escapeHtml(String(value))}</dd>`
    )).join('');
  }

  if (dependencies) {
    dependencies.innerHTML = '';
    const deps = Array.isArray(item.dependencies) ? item.dependencies : [];
    if (deps.length === 0) {
      dependencies.innerHTML = '<li class="text-variant">—</li>';
    } else {
      deps.forEach((dep) => {
        const li = document.createElement('li');
        li.textContent = `${dep.key || ''} ${dep.version || ''}`.trim();
        dependencies.appendChild(li);
      });
    }
  }

  if (actions) {
    actions.innerHTML = '';
    const isFree = item.isFree || item.price === 'Free';
    if (!isFree && key) {
      const trialBtn = document.createElement('button');
      trialBtn.type = 'button';
      trialBtn.className = 'btn btn-primary btn-sm t3js-get-license-trigger';
      trialBtn.dataset.extensionKey = key;
      trialBtn.dataset.glMode = 'trial';
      trialBtn.textContent = 'Free Trial';
      actions.appendChild(trialBtn);
    }
    if (item.liveDemoUrl) {
      const demo = document.createElement('a');
      demo.href = item.liveDemoUrl;
      demo.target = '_blank';
      demo.rel = 'noopener noreferrer';
      demo.className = 'btn btn-default btn-sm';
      demo.textContent = 'Live Demo';
      actions.appendChild(demo);
    }
    if (item.documentationUrl || item.documentation_link) {
      const docs = document.createElement('a');
      docs.href = item.documentationUrl || item.documentation_link;
      docs.target = '_blank';
      docs.rel = 'noopener noreferrer';
      docs.className = 'btn btn-default btn-sm';
      docs.textContent = 'Documentation';
      actions.appendChild(docs);
    }
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn btn-default btn-sm t3js-modal-close';
    closeBtn.dataset.bsDismiss = 'modal';
    closeBtn.textContent = 'Close';
    actions.appendChild(closeBtn);
  }
}

/**
 * @param {HTMLElement} modal
 */
function showModal(modal) {
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modal).show();
  } else if (window.bootstrap?.Modal) {
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
  } else {
    modal.classList.add('show');
    modal.style.display = 'block';
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
      const backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);
    }
  }
}

/**
 * Hide the modal (native API with manual fallback).
 * @param {HTMLElement} modal
 */
function hideModal(modal) {
  if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    bootstrap.Modal.getOrCreateInstance(modal).hide();
  } else if (window.bootstrap?.Modal) {
    window.bootstrap.Modal.getOrCreateInstance(modal).hide();
  } else {
    modal.classList.remove('show');
    modal.style.display = 'none';
    document.body.classList.remove('modal-open');
    document.querySelector('.modal-backdrop')?.remove();
  }
}

// Close via the header (X) or footer Close button (works without Bootstrap data-api).
document.addEventListener('click', (e) => {
  const closer = e.target.closest('#' + MODAL_ID + ' .t3js-modal-close, #' + MODAL_ID + ' [data-bs-dismiss="modal"]');
  if (!closer) {
    return;
  }
  e.preventDefault();
  const modal = document.getElementById(MODAL_ID);
  if (modal) {
    hideModal(modal);
  }
});

document.addEventListener('click', (e) => {
  const trigger = e.target.closest('.t3js-product-detail-trigger');
  if (!trigger) {
    return;
  }
  e.preventDefault();

  const modal = document.getElementById(MODAL_ID);
  if (!modal) {
    return;
  }

  modal.dataset.detailTrigger = '1';
  const extensionKey = trigger.dataset.extensionKey || '';
  const pane = trigger.closest('.tab-pane');
  const script = pane?.querySelector('.catalog-items-json') || document.querySelector('.tab-pane.active .catalog-items-json');
  let items = {};
  try {
    items = JSON.parse(script?.textContent || '{}');
  } catch (err) {
    console.error(err);
  }

  const item = items[extensionKey];
  if (!item) {
    return;
  }

  populateModal(modal, item);
  showModal(modal);
});
