document.querySelectorAll('[data-file-picker]').forEach((input) => {
  const name = input.parentElement?.querySelector('[data-file-name]');
  if (!name) return;

  const updateName = () => {
    const file = input.files?.[0];
    name.textContent = file ? file.name : 'Belum ada berkas dipilih';
    if (file) input.removeAttribute('aria-invalid');
  };

  input.addEventListener('change', updateName);
  input.addEventListener('invalid', () => input.setAttribute('aria-invalid', 'true'));
  updateName();
});
