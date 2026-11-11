(() => {
  // Mobile nav toggle (navbar pills collapse into a dropdown panel)
  const navPills = document.getElementById('navbarPills');
  const navToggle = document.getElementById('navbarToggle');
  navToggle?.addEventListener('click', () => {
    const open = navPills?.classList.toggle('open');
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.querySelectorAll('#navbarPills .nav-pill').forEach(link => {
    link.addEventListener('click', () => {
      navPills?.classList.remove('open');
      navToggle?.setAttribute('aria-expanded', 'false');
    });
  });

  // Account dropdown
  const userMenu = document.getElementById('userMenu');
  const userTrigger = document.getElementById('userTrigger');
  const userDropdown = document.getElementById('userDropdown');
  const closeUserMenu = () => {
    userDropdown?.classList.remove('open');
    userTrigger?.setAttribute('aria-expanded', 'false');
  };
  userTrigger?.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = userDropdown?.classList.toggle('open');
    userTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  document.addEventListener('click', (e) => {
    if (userMenu && !userMenu.contains(e.target)) closeUserMenu();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeUserMenu(); navPills?.classList.remove('open'); }
  });

  document.querySelectorAll('.reveal').forEach((el, i) => {
    el.style.animationDelay = `${Math.min(i * 45, 220)}ms`;
  });

  // Dashboard: quick-view modal for a submission, opened by clicking its name
  // inside a category dropdown or the recent submissions summary.
  const modalOverlay = document.getElementById('entryModalOverlay');
  if (modalOverlay) {
    const field = (id) => document.getElementById(id);
    const modalTitle = field('entryModalTitle');
    const modalCategory = field('entryModalCategory');
    const modalSong = field('entryModalSong');
    const modalPayment = field('entryModalPayment');
    const modalStatus = field('entryModalStatus');
    const modalId = field('entryModalId');
    const modalDate = field('entryModalDate');
    const modalContactName = field('entryModalContactName');
    const modalContactNumber = field('entryModalContactNumber');
    const modalLink = field('entryModalLink');
    const modalClose = field('entryModalClose');
    let lastFocused = null;

    const openModal = (ds) => {
      modalTitle.textContent = ds.artist || '';
      modalCategory.textContent = ds.category || '';
      modalSong.textContent = ds.song || '';
      modalPayment.className = 'payment-pill ' + (ds.paymentClass || '');
      modalPayment.textContent = ds.paymentLabel || '';
      modalStatus.className = 'status-pill ' + (ds.statusClass || '');
      modalStatus.textContent = ds.statusLabel || '';
      modalId.textContent = '#' + (ds.id || '');
      modalDate.textContent = ds.date || '';
      modalContactName.textContent = ds.contactName || '';
      modalContactNumber.textContent = ds.contactNumber || '';
      modalLink.href = ds.url || '#';
      modalOverlay.classList.add('open');
      modalOverlay.setAttribute('aria-hidden', 'false');
      document.body.classList.add('modal-open');
      lastFocused = document.activeElement;
      modalClose?.focus();
    };
    const closeModal = () => {
      modalOverlay.classList.remove('open');
      modalOverlay.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('modal-open');
      if (lastFocused instanceof HTMLElement) lastFocused.focus();
    };

    document.querySelectorAll('[data-modal-trigger]').forEach((btn) => {
      btn.addEventListener('click', () => openModal(btn.dataset));
    });
    modalClose?.addEventListener('click', closeModal);
    modalOverlay.addEventListener('click', (e) => {
      if (e.target === modalOverlay) closeModal();
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && modalOverlay.classList.contains('open')) closeModal();
    });
  }
})();
