(function () {
  'use strict';

  /* ---------- Загрузка фото товара (админка): перетаскивание и выбор файла ---------- */
  function initDropzone() {
    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('image-input');
    const preview = document.getElementById('preview');
    const hint = document.getElementById('dropzone-hint');
    const removeCheckbox = document.getElementById('remove-image');
    if (!dropzone || !input) return;

    function showFile(file) {
      if (!file || !file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = (e) => {
        preview.src = e.target.result;
        preview.hidden = false;
        hint.hidden = true;
      };
      reader.readAsDataURL(file);
      if (removeCheckbox) removeCheckbox.checked = false;
    }

    dropzone.addEventListener('click', () => input.click());
    dropzone.addEventListener('dragover', (e) => {
      e.preventDefault();
      dropzone.style.borderColor = 'var(--lagoon-500)';
    });
    dropzone.addEventListener('dragleave', () => { dropzone.style.borderColor = ''; });
    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzone.style.borderColor = '';
      const file = e.dataTransfer.files[0];
      if (!file) return;
      input.files = e.dataTransfer.files;
      showFile(file);
    });
    input.addEventListener('change', () => {
      if (input.files[0]) showFile(input.files[0]);
    });
  }

  /* ---------- Счётчик количества «− 1 +» (страница товара и корзина) ---------- */
  function initSteppers() {
    document.querySelectorAll('[data-qty]').forEach((box) => {
      const input = box.querySelector('[data-qty-input]');
      const minus = box.querySelector('[data-qty-step="-1"]');
      const plus = box.querySelector('[data-qty-step="1"]');
      const form = input.form;
      const autosubmit = !!form && form.hasAttribute('data-autosubmit-qty');

      const min = () => parseInt(input.min, 10) || 1;
      const max = () => Math.max(min(), parseInt(input.max, 10) || 1);
      const clamp = (v) => Math.max(min(), Math.min(max(), isNaN(v) ? min() : v));

      let committed = clamp(parseInt(input.value, 10));

      function sync() {
        const v = clamp(parseInt(input.value, 10));
        input.value = v;
        minus.disabled = v <= min();
        plus.disabled = v >= max();
        return v;
      }
      function commit() {
        const v = sync();
        if (autosubmit && v !== committed) {
          committed = v;
          form.requestSubmit();
        }
      }

      minus.addEventListener('click', () => { input.value = clamp(parseInt(input.value, 10) - 1); commit(); });
      plus.addEventListener('click', () => { input.value = clamp(parseInt(input.value, 10) + 1); commit(); });
      input.addEventListener('change', commit);
      box.addEventListener('qty:sync', sync);
      sync();
    });
  }

  /* ---------- Выбор размера на странице товара ---------- */
  function initSizeGroups() {
    document.querySelectorAll('[data-size-group]').forEach((group) => {
      const form = group.closest('form');
      const qtyInput = form && form.querySelector('[data-qty-input]');
      const note = document.querySelector('[data-stock-note]');
      group.addEventListener('change', (e) => {
        const radio = e.target;
        if (!radio.matches('input[type=radio]')) return;
        const stock = parseInt(radio.dataset.stock, 10) || 1;
        if (qtyInput) {
          qtyInput.max = stock;
          qtyInput.closest('[data-qty]').dispatchEvent(new Event('qty:sync'));
        }
        if (note) note.textContent = 'Размер ' + radio.value + ': осталось ' + stock + ' шт.';
      });
    });

    // Понятное сообщение вместо стандартного «Выберите один из вариантов».
    document.querySelectorAll('[data-size-required]').forEach((el) => {
      el.addEventListener('invalid', () => el.setCustomValidity('Выберите размер'));
      el.addEventListener('change', () => {
        const scope = el.closest('[data-size-group]') || el.parentElement;
        scope.querySelectorAll('[data-size-required]').forEach((x) => x.setCustomValidity(''));
        el.setCustomValidity('');
      });
    });
  }

  /* ---------- Телефон: «+7 000-000-00-00», префикс +7 стереть нельзя ---------- */
  function initPhones() {
    const PREFIX = '+7 ';

    // Цифры номера без кода страны (до 10).
    function subscriberDigits(raw) {
      let d = raw.replace(/\D/g, '');
      if (/^\s*\+7/.test(raw)) d = d.slice(1);                      // первая «7» — это префикс
      else if (d.length === 11 && (d[0] === '7' || d[0] === '8')) d = d.slice(1); // автозаполнение «89001234567»
      return d.slice(0, 10);
    }
    function format(d) {
      let out = PREFIX;
      if (d.length > 0) out += d.slice(0, 3);
      if (d.length > 3) out += '-' + d.slice(3, 6);
      if (d.length > 6) out += '-' + d.slice(6, 8);
      if (d.length > 8) out += '-' + d.slice(8, 10);
      return out;
    }
    // Сколько цифр номера находится левее позиции pos в строке raw.
    function digitsBefore(raw, pos) {
      let n = (raw.slice(0, pos).match(/\d/g) || []).length;
      if (/^\s*\+7/.test(raw) && pos >= 2) n -= 1;
      return Math.max(0, n);
    }
    function caretFor(out, digitCount) {
      let pos = PREFIX.length;
      let count = 0;
      for (let i = PREFIX.length; i < out.length && count < digitCount; i++) {
        if (/\d/.test(out[i])) count++;
        pos = i + 1;
      }
      return pos;
    }
    function updateValidity(el, d) {
      if (d.length === 0) el.setCustomValidity(el.required ? 'Укажите телефон' : '');
      else if (d.length < 10) el.setCustomValidity('Введите телефон полностью: +7 000-000-00-00');
      else el.setCustomValidity('');
    }
    function render(el, d, caretDigits) {
      el.value = format(d);
      updateValidity(el, d);
      if (caretDigits !== undefined && document.activeElement === el) {
        const pos = caretFor(el.value, caretDigits);
        el.setSelectionRange(pos, pos);
      }
    }

    document.querySelectorAll('[data-phone]').forEach((el) => {
      render(el, subscriberDigits(el.value));

      el.addEventListener('input', () => {
        const raw = el.value;
        const caret = digitsBefore(raw, el.selectionStart);
        const d = subscriberDigits(raw);
        render(el, d, Math.min(caret, d.length));
      });

      el.addEventListener('keydown', (e) => {
        const start = el.selectionStart, end = el.selectionEnd;
        if (e.key === 'Backspace' && start === end && start <= PREFIX.length) e.preventDefault();
        if (e.key === 'Delete' && start === end && start < PREFIX.length) e.preventDefault();
      });

      // Курсор не должен оказываться внутри «+7 ».
      const keepCaretOutOfPrefix = () => {
        if (el.selectionStart === el.selectionEnd && el.selectionStart < PREFIX.length) {
          el.setSelectionRange(PREFIX.length, PREFIX.length);
        }
      };
      el.addEventListener('focus', () => setTimeout(keepCaretOutOfPrefix, 0));
      el.addEventListener('click', keepCaretOutOfPrefix);

      el.addEventListener('paste', (e) => {
        e.preventDefault();
        const text = (e.clipboardData || window.clipboardData).getData('text');
        let p = text.replace(/\D/g, '');
        // 11 цифр: первые «7» или «8» — код страны, убираем.
        if (p.length > 10 && (p[0] === '7' || p[0] === '8')) p = p.slice(1);
        p = p.slice(0, 10);
        if (!p) return;

        const raw = el.value;
        const cur = subscriberDigits(raw);
        let result;
        let caret;
        if (p.length === 10) {
          result = p; // вставлен целый номер — заменяем прежнее содержимое
          caret = 10;
        } else {
          const a = digitsBefore(raw, el.selectionStart);
          const b = digitsBefore(raw, el.selectionEnd);
          result = (cur.slice(0, a) + p + cur.slice(b)).slice(0, 10);
          caret = Math.min(a + p.length, result.length);
        }
        render(el, result, caret);
      });

      el.addEventListener('blur', () => updateValidity(el, subscriberDigits(el.value)));
    });
  }

  /* ---------- Модальные окна <dialog> ---------- */
  function initDialogs() {
    document.querySelectorAll('[data-open-dialog]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const dialog = document.getElementById(btn.dataset.openDialog);
        if (!dialog) return;
        if (typeof dialog.showModal === 'function') dialog.showModal();
        else dialog.setAttribute('open', '');
      });
    });
    document.querySelectorAll('dialog').forEach((dialog) => {
      // После успешного действия (например, запроса отмены) страницу нужно обновить,
      // чтобы показать новый статус заказа.
      const reloadIfDone = () => {
        if (dialog.hasAttribute('data-reload-on-close') && dialog.dataset.done === '1') location.reload();
      };
      const closeDialog = () => {
        if (dialog.close) dialog.close();
        else dialog.removeAttribute('open');
        reloadIfDone();
      };
      dialog.querySelectorAll('[data-close-dialog]').forEach((btn) => btn.addEventListener('click', closeDialog));
      // Клик по затемнённому фону закрывает окно.
      dialog.addEventListener('click', (e) => { if (e.target === dialog) closeDialog(); });
      // Закрытие клавишей Esc.
      dialog.addEventListener('close', reloadIfDone);
    });
  }

  /* ---------- Запрос на отмену заказа: ответ показываем в этом же окне ---------- */
  function initCancelForm() {
    const form = document.querySelector('[data-cancel-form]');
    if (!form) return;
    const dialog = form.closest('dialog');
    const stepForm = form.querySelector('[data-cancel-step="form"]');
    const stepDone = form.querySelector('[data-cancel-step="done"]');
    const errorBox = form.querySelector('[data-cancel-error]');
    const submit = form.querySelector('button[type="submit"]');

    function showError(message) {
      errorBox.textContent = message;
      errorBox.hidden = false;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      errorBox.hidden = true;
      submit.disabled = true;
      try {
        const res = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        const data = await res.json();
        if (data.ok) {
          stepForm.hidden = true;
          stepDone.hidden = false;
          if (dialog) dialog.dataset.done = '1';
        } else {
          showError(data.message || 'Не удалось отправить запрос.');
          submit.disabled = false;
        }
      } catch (err) {
        showError('Не удалось отправить запрос. Проверьте соединение и попробуйте снова.');
        submit.disabled = false;
      }
    });
  }

  /* ---------- Админка: общий остаток = сумма остатков по размерам ---------- */
  function initSizeAdmin() {
    const inputs = document.querySelectorAll('[data-size-stock-input]');
    const total = document.getElementById('stock-input');
    if (!inputs.length || !total) return;

    function recalc() {
      let any = false;
      let sum = 0;
      inputs.forEach((i) => {
        if (i.value.trim() !== '') {
          any = true;
          sum += parseInt(i.value, 10) || 0;
        }
      });
      total.readOnly = any;
      if (any) total.value = sum;
    }
    inputs.forEach((i) => i.addEventListener('input', recalc));
    recalc();
  }

  function init() {
    initDropzone();
    initSteppers();
    initSizeGroups();
    initPhones();
    initDialogs();
    initCancelForm();
    initSizeAdmin();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
