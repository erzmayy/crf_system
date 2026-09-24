/* =====================================================================
   CRF Prototype - script.js
   Validasi client-side ringan + interaksi form.
   Validasi asli tetap dilakukan di server (lihat actions/*.php).
   ===================================================================== */

document.addEventListener('DOMContentLoaded', function () {

  /* -----------------------------------------------------------------
   * 1. Tampilkan field "Detail Kategori" sesuai kategori yang dipilih
   * ----------------------------------------------------------------- */
  var categorySelect = document.getElementById('change_category');
  var categoryDetailWrap = document.getElementById('category-detail-wrap');
  var categoryDetailLabel = document.getElementById('category-detail-label');
  var categoryDetailInput = document.getElementById('change_category_detail');

  var categoryHints = {
    'Aplikasi': 'Nama Aplikasi / Modul (contoh: CL, PKS, PKWT, Absensi)',
    'Infrastruktur': 'Jenis Infrastruktur (contoh: LAN, WAN)',
    'Proses': 'Proses yang dimaksud (contoh: Payroll)',
    'Security': 'Jenis permintaan (contoh: User ID, Password, Kewenangan Menu)',
    'Lainnya': 'Jelaskan kategori perubahan yang dimaksud'
  };

  function updateCategoryDetail() {
    if (!categorySelect) { return; }
    var val = categorySelect.value;

    if (val && categoryHints[val]) {
      categoryDetailWrap.classList.remove('d-none');
      categoryDetailLabel.textContent = 'Detail Kategori - ' + val;
      categoryDetailInput.placeholder = categoryHints[val];
      // Sesuai brief butir 14: hanya kategori "Lainnya" yang wajib diisi.
      categoryDetailInput.required = (val === 'Lainnya');
    } else {
      categoryDetailWrap.classList.add('d-none');
      categoryDetailInput.required = false;
    }
  }

  if (categorySelect) {
    categorySelect.addEventListener('change', updateCategoryDetail);
    updateCategoryDetail(); // set kondisi awal (misalnya saat edit draft)
  }

  /* -----------------------------------------------------------------
   * 2. Nominal anggaran hanya wajib jika salah satu pilihan biaya dipilih
   * ----------------------------------------------------------------- */
  var budgetRadios = document.querySelectorAll('input[name="budget_type"]');
  var budgetAmountInput = document.getElementById('budget_amount');

  function toggleBudgetAmount() {
    var anySelected = false;
    budgetRadios.forEach(function (radio) {
      if (radio.checked) { anySelected = true; }
    });
    if (budgetAmountInput) {
      budgetAmountInput.disabled = !anySelected;
    }
  }

  if (budgetRadios.length) {
    budgetRadios.forEach(function (radio) {
      radio.addEventListener('change', toggleBudgetAmount);
    });
    toggleBudgetAmount();
  }

  /* -----------------------------------------------------------------
   * 3. Tampilkan nama file yang dipilih pada input upload
   * ----------------------------------------------------------------- */
  var fileInput = document.getElementById('attachments');
  var fileList = document.getElementById('file-list-preview');

  if (fileInput && fileList) {
    fileInput.addEventListener('change', function () {
      fileList.innerHTML = '';
      if (fileInput.files.length === 0) { return; }

      var ul = document.createElement('ul');
      ul.className = 'mb-0 ps-3';

      Array.prototype.forEach.call(fileInput.files, function (file) {
        var li = document.createElement('li');
        var sizeKb = Math.round(file.size / 1024);
        li.textContent = file.name + ' (' + sizeKb + ' KB)';
        ul.appendChild(li);
      });

      fileList.appendChild(ul);
    });
  }

  /* -----------------------------------------------------------------
   * 4. Validasi Bootstrap standar untuk form yang butuh validasi
   * ----------------------------------------------------------------- */
  var formsToValidate = document.querySelectorAll('.needs-validation');

  formsToValidate.forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });

  /* -----------------------------------------------------------------
   * 5. Konfirmasi sebelum admin mengubah status ke Solve / Cancel
   * ----------------------------------------------------------------- */
  var statusSelect = document.getElementById('status');

  if (statusSelect) {
    statusSelect.addEventListener('change', function () {
      if (statusSelect.value === 'Solve') {
        if (!confirm('Yakin ingin menandai pengajuan ini sebagai Solve (Selesai)?')) {
          statusSelect.value = statusSelect.dataset.previous || 'Dalam Proses';
        }
      } else if (statusSelect.value === 'Cancel') {
        if (!confirm('Yakin ingin membatalkan pengajuan ini?')) {
          statusSelect.value = statusSelect.dataset.previous || 'Dalam Proses';
        }
      }
    });
    statusSelect.dataset.previous = statusSelect.value;
  }

    /* -----------------------------------------------------------------
   * 6. Simpan Draft
   * ----------------------------------------------------------------- */
  var btnSaveDraft = document.getElementById('btnSaveDraft');
  var crfForm = document.getElementById('crfForm');

  if (btnSaveDraft && crfForm) {
    btnSaveDraft.addEventListener('click', function () {

      // Arahkan form ke proses save draft
      crfForm.action = '../actions/save_draft.php';

      // Kirim form tanpa menjalankan validasi browser / JS
      crfForm.submit();
    });
  }

  /* -----------------------------------------------------------------
   * 7. Toggle sidebar mobile (hamburger + overlay)
   * ----------------------------------------------------------------- */
  var appShell = document.querySelector('.crf-app-shell');
  var sidebarToggle = document.querySelector('.crf-sidebar-toggle');
  var sidebarOverlay = document.querySelector('.crf-sidebar-overlay');

  function closeSidebar() {
    if (appShell) { appShell.classList.remove('crf-sidebar-open'); }
  }

  if (sidebarToggle && appShell) {
    sidebarToggle.addEventListener('click', function () {
      appShell.classList.toggle('crf-sidebar-open');
    });
  }

  if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
  }

  // Tutup sidebar otomatis kalau salah satu link menu diklik (mobile)
  document.querySelectorAll('.crf-sidebar-nav a, .crf-sidebar-footer a').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });

});


