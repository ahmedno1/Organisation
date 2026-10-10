import './bootstrap';

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if ('scrollRestoration' in window.history) {
    window.history.scrollRestoration = 'manual';
}
const THEME_STORAGE_KEY = 'esnad_theme';
let themeToggleBound = false;

const getCurrentTheme = () => (
    document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
);

const readStoredTheme = () => {
    try {
        const stored = localStorage.getItem(THEME_STORAGE_KEY);
        return stored === 'dark' || stored === 'light' ? stored : null;
    } catch (error) {
        return null;
    }
};

const resolvePreferredTheme = () => {
    const storedTheme = readStoredTheme();
    if (storedTheme) {
        return storedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const updateThemeToggleUi = () => {
    const isDark = getCurrentTheme() === 'dark';
    const nextThemeLabel = isDark
        ? i18nText('theme-light', 'Light')
        : i18nText('theme-dark', 'Dark');

    document.querySelectorAll('[data-theme-toggle-label]').forEach((labelEl) => {
        labelEl.textContent = nextThemeLabel;
    });

    document.querySelectorAll('[data-theme-toggle]').forEach((toggleEl) => {
        toggleEl.setAttribute('aria-label', nextThemeLabel);
    });
};

const updateBrowserThemeColor = () => {
    const themeColorMeta = document.getElementById('dynamic-theme-color');
    if (!themeColorMeta) {
        return;
    }

    themeColorMeta.setAttribute('content', getCurrentTheme() === 'dark' ? '#0b1524' : '#0f4c7a');
};

const applyTheme = (theme, { persist = true } = {}) => {
    const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', normalizedTheme);

    if (persist) {
        try {
            localStorage.setItem(THEME_STORAGE_KEY, normalizedTheme);
        } catch (error) {
            // Ignore storage exceptions to avoid breaking the app.
        }
    }

    updateThemeToggleUi();
    updateBrowserThemeColor();
};

const initializeTheme = () => {
    applyTheme(resolvePreferredTheme(), { persist: false });
};

const bindThemeToggle = () => {
    if (themeToggleBound) {
        return;
    }

    themeToggleBound = true;

    document.addEventListener('click', (event) => {
        const toggleButton = event.target.closest('[data-theme-toggle]');
        if (!toggleButton) {
            return;
        }

        event.preventDefault();
        applyTheme(getCurrentTheme() === 'dark' ? 'light' : 'dark');
    });
};

const animateMetricCards = () => {
    if (prefersReducedMotion) {
        return;
    }

    const counters = document.querySelectorAll('.metric-card p');

    counters.forEach((counterEl) => {
        const rawText = counterEl.textContent?.trim() ?? '';
        const target = Number(rawText.replace(/[^\d.-]/g, ''));

        if (!Number.isFinite(target)) {
            return;
        }

        let current = 0;
        const duration = 750;
        const frameMs = 16;
        const steps = Math.max(1, Math.floor(duration / frameMs));
        const increment = target / steps;

        const timer = setInterval(() => {
            current += increment;

            if (current >= target) {
                counterEl.textContent = `${Math.round(target)}`;
                clearInterval(timer);
                return;
            }

            counterEl.textContent = `${Math.round(current)}`;
        }, frameMs);
    });
};

const revealOnScroll = () => {
    const elements = document.querySelectorAll('.card, .metric-card, .form-block, .section-head');

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
        elements.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.08 });

    elements.forEach((el) => {
        el.classList.add('reveal');
        observer.observe(el);
    });
};

const scrollToPageStart = (targetUrl = window.location.href) => {
    try {
        const url = new URL(targetUrl, window.location.origin);
        if (url.hash) {
            return;
        }
    } catch (error) {
        // Ignore URL parsing issues and continue to scroll top.
    }

    window.scrollTo(0, 0);
};

const initializePageInteractions = () => {
    scrollToPageStart();
    updateThemeToggleUi();
    revealOnScroll();
    animateMetricCards();
    setupOfflineQueuedForms();
    updateGlobalConnectionStatus();
    updateGlobalQueueCount();
};

window.addEventListener('DOMContentLoaded', initializePageInteractions);
document.addEventListener('esnad:page-loaded', initializePageInteractions);

if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', async () => {
        try {
            await navigator.serviceWorker.register('/sw.js');
        } catch (error) {
            console.error('Service worker registration failed:', error);
        }
    });
}

const OFFLINE_QUEUE_KEY = 'family_offline_queue_v1';
const OFFLINE_QUEUE_COUNT_KEY = 'family_offline_queue_count_v1';
const OFFLINE_ARCHIVE_KEY = 'family_offline_archive_v1';
const OFFLINE_SECRET_KEY = 'family_offline_secret_v1';
const OFFLINE_SYNC_INTERVAL_MS = 2 * 60 * 1000;
const OFFLINE_ARCHIVE_TTL_MS = 30 * 24 * 60 * 60 * 1000;
const OFFLINE_ARCHIVE_MAX_ITEMS = 200;
const OFFLINE_FILES_DB = 'esnad_offline_files_db';
const OFFLINE_FILES_STORE = 'files';

let offlineSyncInProgress = false;
let spaNavigationBound = false;
let spaNavigationInProgress = false;
let busyUiTimer = null;

const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder();
const i18nText = (key, fallback = '') => document.body?.getAttribute(`data-i18n-${key}`) || fallback;

const setUiBusy = (isBusy) => {
    if (busyUiTimer) {
        clearTimeout(busyUiTimer);
        busyUiTimer = null;
    }

    if (isBusy) {
        document.body.classList.add('is-busy');
        return;
    }

    busyUiTimer = setTimeout(() => {
        document.body.classList.remove('is-busy');
    }, 180);
};

const runInlineScripts = (root) => {
    if (!root) {
        return;
    }

    root.querySelectorAll('script').forEach((oldScript) => {
        const newScript = document.createElement('script');

        Array.from(oldScript.attributes).forEach((attr) => {
            newScript.setAttribute(attr.name, attr.value);
        });

        // Preserve script execution order during SPA navigation.
        // Dynamically inserted external scripts are async by default, which can
        // make inline page scripts run before their dependencies (e.g. Chart.js).
        if (newScript.src) {
            newScript.async = false;
        }

        newScript.textContent = oldScript.textContent;
        oldScript.replaceWith(newScript);
    });
};

const replaceSelector = (selector, sourceDoc, { insertAfter = null } = {}) => {
    const current = document.querySelector(selector);
    const next = sourceDoc.querySelector(selector);

    if (current && next) {
        current.replaceWith(next.cloneNode(true));
        return;
    }

    if (current && !next) {
        current.remove();
        return;
    }

    if (!current && next && insertAfter) {
        const anchor = document.querySelector(insertAfter);
        if (anchor?.parentNode) {
            anchor.parentNode.insertBefore(next.cloneNode(true), anchor.nextSibling);
        }
    }
};

const applySpaDocument = (sourceDoc) => {
    document.title = sourceDoc.title;

    document.documentElement.lang = sourceDoc.documentElement.lang || document.documentElement.lang;
    document.documentElement.dir = sourceDoc.documentElement.dir || document.documentElement.dir;

    Array.from(sourceDoc.body.attributes).forEach((attr) => {
        if (attr.name.startsWith('data-i18n-')) {
            document.body.setAttribute(attr.name, attr.value);
        }
    });

    replaceSelector('.topbar', sourceDoc);
    replaceSelector('.nav', sourceDoc, { insertAfter: '.topbar' });
    replaceSelector('main.main-content', sourceDoc);
    replaceSelector('.footer', sourceDoc);
    replaceSelector('.quick-contact', sourceDoc);
    replaceSelector('#page-scripts', sourceDoc);

    runInlineScripts(document.getElementById('page-scripts'));
    document.dispatchEvent(new CustomEvent('esnad:page-loaded'));
};

const resetScrollAfterNavigation = (targetUrl) => {
    scrollToPageStart(targetUrl);
};

const canHandleSpaLink = (link) => {
    if (!link) {
        return false;
    }

    if (link.hasAttribute('download') || link.dataset.spa === 'false') {
        return false;
    }

    if (link.target && link.target !== '_self') {
        return false;
    }

    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:')) {
        return false;
    }

    const url = new URL(link.href, window.location.origin);
    if (url.origin !== window.location.origin) {
        return false;
    }

    if (url.pathname.includes('export') || url.pathname.includes('download')) {
        return false;
    }

    return true;
};

const canHandleSpaForm = (form) => {
    if (!form || form.dataset.spa === 'false') {
        return false;
    }

    const target = (form.getAttribute('target') || '').trim();
    if (target !== '' && target !== '_self') {
        return false;
    }

    const action = form.getAttribute('action') || window.location.href;
    const url = new URL(action, window.location.origin);
    if (url.origin !== window.location.origin) {
        return false;
    }

    return true;
};

const fetchSpaDocument = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'text/html,application/xhtml+xml',
            ...(options.headers || {}),
        },
        ...options,
    });

    const contentType = (response.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('text/html')) {
        window.location.href = response.url || url;
        return null;
    }

    const html = await response.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');

    return { doc, finalUrl: response.url || url };
};

const navigateSpa = async (url, { replace = false } = {}) => {
    if (spaNavigationInProgress) {
        return;
    }

    spaNavigationInProgress = true;
    setUiBusy(true);
    try {
        const payload = await fetchSpaDocument(url, { method: 'GET' });
        if (!payload) {
            return;
        }

        applySpaDocument(payload.doc);
        resetScrollAfterNavigation(payload.finalUrl);

        if (replace) {
            window.history.replaceState({}, '', payload.finalUrl);
        } else {
            window.history.pushState({}, '', payload.finalUrl);
        }
    } catch (error) {
        window.location.href = url;
    } finally {
        spaNavigationInProgress = false;
        setUiBusy(false);
    }
};

const submitSpaForm = async (form) => {
    if (spaNavigationInProgress) {
        return;
    }

    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const action = form.getAttribute('action') || window.location.href;
    const url = new URL(action, window.location.origin);

    let fetchUrl = url.toString();
    const fetchOptions = {
        method,
    };

    if (method === 'GET') {
        const params = new URLSearchParams(new FormData(form));
        if (params.toString()) {
            fetchUrl = `${url.pathname}?${params.toString()}`;
        } else {
            fetchUrl = url.pathname;
        }
    } else {
        fetchOptions.body = new FormData(form);
    }

    spaNavigationInProgress = true;
    setUiBusy(true);
    try {
        const payload = await fetchSpaDocument(fetchUrl, fetchOptions);
        if (!payload) {
            return;
        }

        applySpaDocument(payload.doc);
        window.history.pushState({}, '', payload.finalUrl);
        resetScrollAfterNavigation(payload.finalUrl);
    } catch (error) {
        form.submit();
    } finally {
        spaNavigationInProgress = false;
        setUiBusy(false);
    }
};

const bindSpaNavigation = () => {
    if (spaNavigationBound) {
        return;
    }

    spaNavigationBound = true;

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const link = event.target.closest('a[href]');
        if (!canHandleSpaLink(link)) {
            return;
        }

        event.preventDefault();
        navigateSpa(link.href);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !canHandleSpaForm(form)) {
            return;
        }

        if (!navigator.onLine && form.dataset.offlineQueue === 'enabled') {
            return;
        }

        event.preventDefault();
        submitSpaForm(form);
    });

    window.addEventListener('popstate', () => {
        navigateSpa(window.location.href, { replace: true });
    });
};

const getOfflineSecret = () => {
    let secret = localStorage.getItem(OFFLINE_SECRET_KEY);
    if (secret) {
        return secret;
    }

    const bytes = new Uint8Array(32);
    window.crypto.getRandomValues(bytes);
    secret = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    localStorage.setItem(OFFLINE_SECRET_KEY, secret);
    return secret;
};

const bytesToBase64 = (bytes) => {
    let binary = '';
    bytes.forEach((b) => {
        binary += String.fromCharCode(b);
    });
    return btoa(binary);
};

const base64ToBytes = (base64) => {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
};

const xorBytes = (inputBytes, secretBytes) => {
    const out = new Uint8Array(inputBytes.length);
    for (let i = 0; i < inputBytes.length; i++) {
        out[i] = inputBytes[i] ^ secretBytes[i % secretBytes.length];
    }
    return out;
};

const encryptJson = (value) => {
    const json = JSON.stringify(value);
    const plainBytes = textEncoder.encode(json);
    const secretBytes = textEncoder.encode(getOfflineSecret());
    const encryptedBytes = xorBytes(plainBytes, secretBytes);
    return bytesToBase64(encryptedBytes);
};

const decryptJson = (encoded) => {
    const encryptedBytes = base64ToBytes(encoded);
    const secretBytes = textEncoder.encode(getOfflineSecret());
    const plainBytes = xorBytes(encryptedBytes, secretBytes);
    return JSON.parse(textDecoder.decode(plainBytes));
};

const readEncryptedArray = (key) => {
    const raw = localStorage.getItem(key);
    if (!raw) {
        return [];
    }

    try {
        const parsed = decryptJson(raw);
        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        localStorage.removeItem(key);
        return [];
    }
};

const writeEncryptedArray = (key, value) => {
    localStorage.setItem(key, encryptJson(value));
};

const dispatchQueueChanged = () => {
    window.dispatchEvent(new CustomEvent('esnad-offline-queue-changed', {
        detail: {
            count: getQueueCount(),
            syncing: offlineSyncInProgress,
        },
    }));
};

const readQueue = () => readEncryptedArray(OFFLINE_QUEUE_KEY);

const writeQueue = (queue) => {
    writeEncryptedArray(OFFLINE_QUEUE_KEY, queue);
    localStorage.setItem(OFFLINE_QUEUE_COUNT_KEY, String(queue.length));
    dispatchQueueChanged();
};

const readArchive = () => readEncryptedArray(OFFLINE_ARCHIVE_KEY);

const writeArchive = (archive) => {
    writeEncryptedArray(OFFLINE_ARCHIVE_KEY, archive);
};

const getQueueCount = () => {
    const raw = localStorage.getItem(OFFLINE_QUEUE_COUNT_KEY);
    const count = raw !== null ? Number(raw) : NaN;
    if (Number.isFinite(count) && count >= 0) {
        return count;
    }
    const queue = readQueue();
    localStorage.setItem(OFFLINE_QUEUE_COUNT_KEY, String(queue.length));
    return queue.length;
};

const openOfflineFilesDb = () => new Promise((resolve, reject) => {
    const req = indexedDB.open(OFFLINE_FILES_DB, 1);
    req.onupgradeneeded = () => {
        const db = req.result;
        if (!db.objectStoreNames.contains(OFFLINE_FILES_STORE)) {
            db.createObjectStore(OFFLINE_FILES_STORE, { keyPath: 'id' });
        }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
});

const putOfflineFile = async (file) => {
    const db = await openOfflineFilesDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(OFFLINE_FILES_STORE, 'readwrite');
        const store = tx.objectStore(OFFLINE_FILES_STORE);
        const id = `file-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
        store.put({ id, file });
        tx.oncomplete = () => resolve(id);
        tx.onerror = () => reject(tx.error);
    });
};

const getOfflineFile = async (id) => {
    const db = await openOfflineFilesDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(OFFLINE_FILES_STORE, 'readonly');
        const store = tx.objectStore(OFFLINE_FILES_STORE);
        const req = store.get(id);
        req.onsuccess = () => resolve(req.result?.file ?? null);
        req.onerror = () => reject(req.error);
    });
};

const deleteOfflineFile = async (id) => {
    const db = await openOfflineFilesDb();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(OFFLINE_FILES_STORE, 'readwrite');
        const store = tx.objectStore(OFFLINE_FILES_STORE);
        store.delete(id);
        tx.oncomplete = () => resolve();
        tx.onerror = () => reject(tx.error);
    });
};

const serializeFormForQueue = async (formEl) => {
    const formData = new FormData(formEl);
    const hasFiles = Array.from(formData.values()).some((value) => value instanceof File && value.size > 0);

    if (hasFiles) {
        const fields = [];
        const files = [];

        // Persist file blobs in IndexedDB and keep only references in localStorage queue.
        for (const [key, value] of formData.entries()) {
            if (value instanceof File && value.size > 0) {
                const fileId = await putOfflineFile(value);
                files.push({
                    key,
                    file_id: fileId,
                    name: value.name,
                    type: value.type,
                    last_modified: value.lastModified,
                });
                continue;
            }

            fields.push([key, String(value)]);
        }

        return { mode: 'multipart', fields, files };
    }

    const params = new URLSearchParams();

    formData.forEach((value, key) => {
        if (value instanceof File) {
            return;
        }
        params.append(key, String(value));
    });

    return { mode: 'urlencoded', payload: params.toString() };
};

const enqueueFormSubmission = async (formEl) => {
    if (!formEl) {
        return { ok: false, reason: 'invalid_form' };
    }

    const serialized = await serializeFormForQueue(formEl);
    if (!serialized || (serialized.mode === 'urlencoded' && !serialized.payload)) {
        return { ok: false, reason: 'empty_payload' };
    }

    const queue = readQueue();
    if (serialized.mode === 'urlencoded') {
        const params = new URLSearchParams(serialized.payload);
        const isFamiliesStore = /\/families\/?$/.test(formEl.action);
        const headNationalId = (params.get('head_national_id') || '').trim();
        const institutionId = (params.get('institution_id') || '').trim();

        if (isFamiliesStore && headNationalId !== '') {
            const alreadyQueued = queue.some((queuedItem) => {
                if (queuedItem.mode !== 'urlencoded' || typeof queuedItem.payload !== 'string') {
                    return false;
                }
                if (!/\/families\/?$/.test(queuedItem.action || '')) {
                    return false;
                }

                const queuedParams = new URLSearchParams(queuedItem.payload);
                const queuedHeadId = (queuedParams.get('head_national_id') || '').trim();
                const queuedInstitutionId = (queuedParams.get('institution_id') || '').trim();
                return queuedHeadId !== '' &&
                    queuedHeadId === headNationalId &&
                    queuedInstitutionId === institutionId;
            });

            if (alreadyQueued) {
                return { ok: false, reason: 'duplicate_family' };
            }
        }
    }

    const item = {
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        action: formEl.action,
        mode: serialized.mode,
        created_at: new Date().toISOString(),
        last_error: null,
    };
    if (serialized.mode === 'multipart') {
        item.fields = serialized.fields;
        item.files = serialized.files;
    } else {
        item.payload = serialized.payload;
    }

    queue.push(item);
    writeQueue(queue);
    return { ok: true };
};

const flattenValidationErrors = (errors) => {
    if (!errors || typeof errors !== 'object') {
        return [];
    }

    return Object.values(errors)
        .flat()
        .filter((message) => typeof message === 'string' && message.trim() !== '');
};

const sendQueuedItem = async (item) => {
    let response;
    if (item.mode === 'multipart') {
        const formData = new FormData();
        (item.fields ?? []).forEach(([key, value]) => {
            formData.append(key, value);
        });

        for (const fileRef of (item.files ?? [])) {
            const storedFile = await getOfflineFile(fileRef.file_id);
            if (!storedFile) {
                const missingError = new Error(i18nText('offline-file-missing', 'Attached file is missing from local storage.'));
                missingError.status = 422;
                missingError.validation = [i18nText('offline-file-lookup-error', 'Could not find the local attached file.')];
                throw missingError;
            }

            formData.append(fileRef.key, storedFile, fileRef.name);
        }

        response = await fetch(item.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
            credentials: 'same-origin',
        });
    } else {
        response = await fetch(item.action, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: item.payload,
            credentials: 'same-origin',
        });
    }

    const defaultMessage = i18nText('offline-upload-error', 'Failed to upload a queued family record.');
    let responseData = null;

    try {
        responseData = await response.clone().json();
    } catch (error) {
        responseData = null;
    }

    if (response.ok) {
        return responseData;
    }

    const validationMessages = flattenValidationErrors(responseData?.errors);
    const message = typeof responseData?.message === 'string' && responseData.message.trim() !== ''
        ? responseData.message
        : (validationMessages[0] || defaultMessage);

    const error = new Error(message);
    error.status = response.status;
    error.validation = validationMessages;
    throw error;
};

const buildArchiveItem = (item, responseData) => {
    const params = new URLSearchParams(item.payload || '');
    const hasAttachments = item.mode === 'multipart' && Array.isArray(item.files) && item.files.length > 0;

    return {
        id: item.id,
        action: item.action,
        created_at: item.created_at,
        synced_at: new Date().toISOString(),
        family_id: responseData?.family_id ?? null,
        head_name: params.get('head_name') || null,
        head_national_id: params.get('head_national_id') || null,
        institution_id: params.get('institution_id') || null,
        has_attachments: hasAttachments,
    };
};

const archiveSyncedItem = (item, responseData) => {
    const now = Date.now();
    const minAllowedTime = now - OFFLINE_ARCHIVE_TTL_MS;

    const archive = readArchive()
        .filter((entry) => {
            const ts = Date.parse(entry?.synced_at || '');
            return Number.isFinite(ts) && ts >= minAllowedTime;
        });

    archive.unshift(buildArchiveItem(item, responseData));
    writeArchive(archive.slice(0, OFFLINE_ARCHIVE_MAX_ITEMS));
};

const syncQueue = async ({ manual = false } = {}) => {
    if (offlineSyncInProgress || !navigator.onLine) {
        dispatchQueueChanged();
        return { synced: 0, remaining: getQueueCount(), lastError: null };
    }

    let queue = readQueue();
    if (!queue.length) {
        dispatchQueueChanged();
        return { synced: 0, remaining: 0, lastError: null };
    }

    offlineSyncInProgress = true;
    dispatchQueueChanged();

    let synced = 0;
    let lastError = null;

    while (queue.length) {
        const item = queue[0];

        try {
            const responseData = await sendQueuedItem(item);
            if (item.mode === 'multipart') {
                await Promise.all((item.files ?? []).map((fileRef) => deleteOfflineFile(fileRef.file_id)));
            }
            queue.shift();
            synced++;
            writeQueue(queue);
            archiveSyncedItem(item, responseData);
        } catch (error) {
            queue[0] = {
                ...item,
                last_error: {
                    at: new Date().toISOString(),
                    message: error.message || i18nText('offline-unknown-error', 'Unknown sync error.'),
                    status: error.status ?? null,
                    validation: Array.isArray(error.validation) ? error.validation : [],
                },
            };
            writeQueue(queue);
            lastError = queue[0].last_error;
            break;
        }
    }

    offlineSyncInProgress = false;
    dispatchQueueChanged();

    window.dispatchEvent(new CustomEvent('esnad-offline-sync-finished', {
        detail: {
            synced,
            remaining: queue.length,
            lastError,
            manual,
        },
    }));

    return { synced, remaining: queue.length, lastError };
};

const exportQueue = () => {
    const queue = readQueue();
    return JSON.stringify({
        version: 1,
        exported_at: new Date().toISOString(),
        count: queue.length,
        items: queue,
    }, null, 2);
};

const importQueue = (jsonText) => {
    const parsed = JSON.parse(jsonText);
    const items = Array.isArray(parsed?.items) ? parsed.items : [];

    const sanitized = items.filter((item) => (
        item &&
        typeof item.action === 'string' &&
        item.action.trim() !== '' &&
        (
            (item.mode === 'multipart' && Array.isArray(item.fields) && Array.isArray(item.files)) ||
            (typeof item.payload === 'string' && item.payload.trim() !== '')
        )
    )).map((item) => ({
        id: item.id || `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        action: item.action,
        mode: item.mode === 'multipart' ? 'multipart' : 'urlencoded',
        payload: item.payload,
        fields: Array.isArray(item.fields) ? item.fields : [],
        files: Array.isArray(item.files) ? item.files : [],
        created_at: item.created_at || new Date().toISOString(),
        last_error: item.last_error ?? null,
    }));

    if (!sanitized.length) {
        return { added: 0, total: getQueueCount() };
    }

    const queue = readQueue();
    const seen = new Set(queue.map((item) => `${item.action}::${item.payload || JSON.stringify(item.fields)}`));
    let added = 0;

    sanitized.forEach((item) => {
        const key = `${item.action}::${item.payload || JSON.stringify(item.fields)}`;
        if (seen.has(key)) {
            return;
        }
        seen.add(key);
        queue.push(item);
        added++;
    });

    writeQueue(queue);
    return { added, total: queue.length };
};

const getLastQueueError = () => {
    const queue = readQueue();
    const first = queue[0];
    return first?.last_error ?? null;
};

const getArchiveCount = () => readArchive().length;

const mapFieldsToObject = (fields) => {
    const data = {};
    (fields || []).forEach(([key, value]) => {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    });
    return data;
};

const getQueueItems = () => {
    return readQueue().map((item) => {
        const base = {
            id: item.id,
            action: item.action,
            mode: item.mode || 'urlencoded',
            created_at: item.created_at,
            last_error: item.last_error ?? null,
        };

        if (item.mode === 'multipart') {
            const fields = mapFieldsToObject(item.fields);
            return {
                ...base,
                head_name: fields.head_name || fields.name || null,
                head_national_id: fields.head_national_id || null,
                files_count: Array.isArray(item.files) ? item.files.length : 0,
            };
        }

        const params = new URLSearchParams(item.payload || '');
        return {
            ...base,
            head_name: params.get('head_name') || params.get('name') || null,
            head_national_id: params.get('head_national_id') || null,
            files_count: 0,
        };
    });
};

const removeQueueItem = async (id) => {
    const queue = readQueue();
    const targetIndex = queue.findIndex((item) => item.id === id);
    if (targetIndex < 0) {
        return false;
    }

    const [removed] = queue.splice(targetIndex, 1);
    if (removed?.mode === 'multipart') {
        await Promise.all((removed.files ?? []).map((fileRef) => deleteOfflineFile(fileRef.file_id)));
    }

    writeQueue(queue);
    return true;
};

const retryQueueItem = async (id) => {
    const queue = readQueue();
    const targetIndex = queue.findIndex((item) => item.id === id);
    if (targetIndex < 0) {
        return { synced: 0, remaining: queue.length, lastError: null };
    }

    if (targetIndex > 0) {
        const [item] = queue.splice(targetIndex, 1);
        queue.unshift(item);
        writeQueue(queue);
    }

    return syncQueue({ manual: true });
};

const updateGlobalConnectionStatus = () => {
    const el = document.getElementById('global-connection-status');
    if (!el) {
        return;
    }

    const online = navigator.onLine;
    el.textContent = i18nText('offline', 'Offline');
    el.classList.add('offline');
    el.classList.remove('online');

    if (!window.__esnadConnectionTimer) {
        window.__esnadConnectionTimer = null;
    }

    if (window.__esnadConnectionTimer) {
        clearTimeout(window.__esnadConnectionTimer);
        window.__esnadConnectionTimer = null;
    }

    if (online) {
        el.classList.add('is-hidden');
        return;
    }

    el.classList.remove('is-hidden');
};

const updateGlobalQueueCount = () => {
    const el = document.getElementById('global-offline-queue-count');
    if (!el) {
        return;
    }

    const count = getQueueCount();
    if (count <= 0) {
        el.hidden = true;
        return;
    }

    el.hidden = false;
    const queueTemplate = i18nText('queue-label', 'Sync queue: __COUNT__');
    el.textContent = queueTemplate.replace('__COUNT__', String(count));
};

const setupOfflineUiBindings = () => {
    updateGlobalConnectionStatus();
    updateGlobalQueueCount();

    window.addEventListener('online', () => {
        updateGlobalConnectionStatus();
        syncQueue();
    });

    window.addEventListener('offline', updateGlobalConnectionStatus);
    window.addEventListener('storage', (event) => {
        if (event.key === OFFLINE_QUEUE_COUNT_KEY || event.key === OFFLINE_QUEUE_KEY) {
            updateGlobalQueueCount();
        }
    });

    window.addEventListener('esnad-offline-queue-changed', updateGlobalQueueCount);
};

const setupCapacitorOfflineSyncBridge = () => {
    const capacitor = window.Capacitor;
    if (!capacitor || typeof capacitor.isNativePlatform !== 'function' || !capacitor.isNativePlatform()) {
        return;
    }

    const runSync = () => {
        if (!navigator.onLine || !window.EsnadOfflineQueue?.syncQueue) {
            return;
        }

        window.EsnadOfflineQueue.syncQueue().catch(() => {
            // Silent fail: regular interval + next online event will retry.
        });
    };

    const dispatchConnectivity = (connected) => {
        window.dispatchEvent(new Event(connected ? 'online' : 'offline'));
        if (connected) {
            runSync();
        }
    };

    const registerListener = (plugin, eventName, callback) => {
        if (!plugin || typeof plugin.addListener !== 'function') {
            return;
        }

        const listenerResult = plugin.addListener(eventName, callback);
        if (listenerResult && typeof listenerResult.then === 'function') {
            listenerResult.catch(() => {
                // Ignore plugin/listener setup failures.
            });
        }
    };

    const plugins = capacitor.Plugins || {};
    const networkPlugin = plugins.Network;
    const appPlugin = plugins.App;

    if (networkPlugin) {
        registerListener(networkPlugin, 'networkStatusChange', (status) => {
            dispatchConnectivity(Boolean(status?.connected));
        });

        if (typeof networkPlugin.getStatus === 'function') {
            networkPlugin.getStatus()
                .then((status) => dispatchConnectivity(Boolean(status?.connected)))
                .catch(() => {
                    // Ignore and rely on browser online/offline events.
                });
        }
    }

    if (appPlugin) {
        registerListener(appPlugin, 'appStateChange', ({ isActive }) => {
            if (isActive) {
                runSync();
            }
        });

        registerListener(appPlugin, 'resume', () => {
            runSync();
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            runSync();
        }
    });

    window.addEventListener('esnad:page-loaded', runSync);
    runSync();
};

const showOfflineGlobalNotice = (message, type = 'success') => {
    const container = document.querySelector('main.main-content');
    if (!container) {
        return;
    }

    let alertBox = document.getElementById('offline-global-alert');
    if (!alertBox) {
        alertBox = document.createElement('div');
        alertBox.id = 'offline-global-alert';
        container.insertBefore(alertBox, container.firstChild);
    }

    alertBox.className = `alert ${type === 'error' ? 'danger' : 'success'}`;
    alertBox.textContent = message;
};

const setupOfflineQueuedForms = () => {
    document.querySelectorAll('form[data-offline-queue=\"enabled\"]').forEach((formEl) => {
        if (formEl.dataset.offlineQueueBound === '1') {
            return;
        }
        formEl.dataset.offlineQueueBound = '1';

        formEl.addEventListener('submit', async (event) => {
            if (navigator.onLine) {
                return;
            }

            event.preventDefault();
            const result = await enqueueFormSubmission(formEl);
            if (result.ok) {
                showOfflineGlobalNotice(i18nText('offline-saved', 'Request saved locally and will sync automatically when back online.'));
                formEl.reset();
            } else if (result.reason === 'duplicate_family') {
                showOfflineGlobalNotice(i18nText('offline-duplicate', 'This record is already queued locally.'), 'error');
            } else {
                showOfflineGlobalNotice(i18nText('offline-save-failed', 'Failed to save request locally.'), 'error');
            }
        });
    });
};

window.EsnadOfflineQueue = {
    enqueueFormSubmission,
    syncQueue,
    getQueueCount,
    getQueueItems,
    removeQueueItem,
    retryQueueItem,
    getLastQueueError,
    getArchiveCount,
    isSyncing: () => offlineSyncInProgress,
    exportQueue,
    importQueue,
};

setupOfflineUiBindings();
setupCapacitorOfflineSyncBridge();
initializeTheme();
bindThemeToggle();
bindSpaNavigation();
window.addEventListener('beforeunload', () => setUiBusy(true));
document.addEventListener('submit', (event) => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form) {
        return;
    }

    // Keep instant-download/export forms snappy without loader overlay.
    const action = (form.getAttribute('action') || '').toLowerCase();
    if (action.includes('export') || action.includes('download')) {
        return;
    }

    setUiBusy(true);
});
setInterval(() => {
    syncQueue();
}, OFFLINE_SYNC_INTERVAL_MS);

if (navigator.onLine) {
    syncQueue();
}
