(function () {
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

  dropzone.addEventListener('dragleave', () => {
    dropzone.style.borderColor = '';
  });

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
})();
