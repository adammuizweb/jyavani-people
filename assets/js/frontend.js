(function () {
  'use strict';

  var profile = document.querySelector('[data-jyp-profile]');
  if (!profile) return;
  profile.classList.add('jyp-enhanced');

  var tablist = profile.querySelector('.jyp-tabs');
  var tabs = Array.prototype.slice.call(profile.querySelectorAll('.jyp-tabs a'));
  var panels = Array.prototype.slice.call(profile.querySelectorAll('[data-jyp-panel]'));
  if (tablist) tablist.setAttribute('role', 'tablist');
  tabs.forEach(function (tab) {
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', tab.getAttribute('href').slice(1));
  });
  panels.forEach(function (panel) {
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', 'jyp-tab-' + panel.id);
    panel.tabIndex = 0;
  });
  profile.querySelectorAll('[data-jyp-enhancement]').forEach(function (control) { control.hidden = false; });

  function activate(id, updateHistory) {
    var target = panels.some(function (panel) { return panel.id === id; }) ? id : 'overview';
    tabs.forEach(function (tab) {
      var selected = tab.getAttribute('href') === '#' + target;
      tab.setAttribute('aria-selected', selected ? 'true' : 'false');
      tab.tabIndex = selected ? 0 : -1;
    });
    panels.forEach(function (panel) { panel.hidden = panel.id !== target; });
    if (updateHistory && window.history && window.history.pushState && window.location.hash !== '#' + target) {
      window.history.pushState(null, '', '#' + target);
    }
  }

  tabs.forEach(function (tab, index) {
    tab.addEventListener('click', function (event) {
      event.preventDefault();
      activate(tab.getAttribute('href').slice(1), true);
    });
    tab.addEventListener('keydown', function (event) {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      var next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
      tabs[next].focus();
      tabs[next].click();
    });
  });
  activate(window.location.hash.slice(1), false);
  window.addEventListener('hashchange', function () { activate(window.location.hash.slice(1), false); });
  window.addEventListener('popstate', function () { activate(window.location.hash.slice(1), false); });

  profile.querySelectorAll('[data-jyp-year]').forEach(function (select) {
    select.addEventListener('change', function () {
      var panel = select.closest('[data-jyp-panel]');
      var visible = 0;
      panel.querySelectorAll('[data-year]').forEach(function (entry) {
        entry.hidden = select.value !== '' && entry.getAttribute('data-year') !== select.value;
        if (!entry.hidden) visible += 1;
      });
      var empty = panel.querySelector('[data-jyp-empty-year]');
      if (empty) empty.hidden = visible !== 0;
    });
  });

  var share = profile.querySelector('[data-jyp-share]');
  var shareStatus = profile.querySelector('[data-jyp-share-status]');
  var canCopy = !!(navigator.clipboard && navigator.clipboard.writeText) || (document.queryCommandSupported && document.queryCommandSupported('copy'));

  function copyText(value) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(value);
    return new Promise(function (resolve, reject) {
      var field = document.createElement('textarea');
      field.value = value;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.opacity = '0';
      document.body.appendChild(field);
      field.select();
      try {
        if (!document.execCommand('copy')) throw new Error('Copy is unavailable');
        resolve();
      } catch (error) {
        reject(error);
      } finally {
        field.remove();
      }
    });
  }

  function showShareStatus(message) {
    share.textContent = message;
    if (shareStatus) shareStatus.textContent = message;
    window.setTimeout(function () {
      share.textContent = share.getAttribute('data-share-label');
      if (shareStatus) shareStatus.textContent = '';
    }, 1800);
  }

  if (share && (navigator.share || canCopy)) {
    share.hidden = false;
    share.addEventListener('click', function () {
      var data = {title: document.title, url: window.location.href};
      if (navigator.share) {
        navigator.share(data).catch(function (error) {
          if (error && error.name === 'AbortError') return;
          if (!canCopy) {
            showShareStatus(share.getAttribute('data-copy-error-label'));
            return;
          }
          copyText(data.url).then(function () {
            showShareStatus(share.getAttribute('data-copied-label'));
          }).catch(function () { showShareStatus(share.getAttribute('data-copy-error-label')); });
        });
        return;
      }
      copyText(data.url).then(function () {
        showShareStatus(share.getAttribute('data-copied-label'));
      }).catch(function () { showShareStatus(share.getAttribute('data-copy-error-label')); });
    });
  }
})();
