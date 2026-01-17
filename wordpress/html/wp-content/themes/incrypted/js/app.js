document.addEventListener('DOMContentLoaded', function () {
  const faqQuestions = document.querySelectorAll('.faq_question');

  document.querySelectorAll('.faq_item.active .faq_answer').forEach(answer => {
    answer.style.maxHeight = answer.scrollHeight + 'px';
  });

  faqQuestions.forEach(question => {
    question.addEventListener('click', function () {
      const faqItem = this.closest('.faq_item');
      const faqAnswer = faqItem.querySelector('.faq_answer');
      const isActive = faqItem.classList.contains('active');

      document.querySelectorAll('.faq_item.active').forEach(activeItem => {
        if (activeItem !== faqItem) {
          const activeAnswer = activeItem.querySelector('.faq_answer');
          activeAnswer.style.maxHeight = '0px';
          activeItem.classList.remove('active');
        }
      });

      if (isActive) {
        faqAnswer.style.maxHeight = '0px';
        faqItem.classList.remove('active');
      } else {
        faqItem.classList.add('active');
        setTimeout(() => {
          faqAnswer.style.maxHeight = faqAnswer.scrollHeight + 'px';
        }, 10);
      }
    });
  });

  window.addEventListener('resize', function () {
    document.querySelectorAll('.faq_item.active .faq_answer').forEach(answer => {
      answer.style.maxHeight = answer.scrollHeight + 'px';
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('np-node-modal');
  if (!modal) {
    return;
  }

  const closeButtons = modal.querySelectorAll('[data-np-modal-close]');
  const content = modal.querySelector('.np-modal__content');
  const actionLink = modal.querySelector('[data-np-modal-add-to-cart]');
  const priceEl = modal.querySelector('.np-modal__price');
  const logoEl = modal.querySelector('.np-modal__logo');
  const discordLink = modal.querySelector('[data-np-modal-discord]');
  const telegramLink = modal.querySelector('[data-np-modal-telegram]');
  const twitterLink = modal.querySelector('[data-np-modal-twitter]');
  const guideLink = modal.querySelector('[data-np-modal-guide]');
  const pillsContainer = modal.querySelector('.np-modal__pills');
  let lastTrigger = null;

  function openModal(html, title) {
    if (content && html !== undefined) {
      content.innerHTML = html;
    }
    const titleEl = modal.querySelector('.np-modal__title');
    if (titleEl && title) {
      titleEl.textContent = title;
    }
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('np-modal-open');
    const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (focusables.length) {
      focusables[0].focus();
    }
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('np-modal-open');
    if (lastTrigger && typeof lastTrigger.focus === 'function') {
      lastTrigger.focus();
    }
  }

  function setActionLink(url, text) {
    if (!actionLink) {
      return;
    }
    if (url) {
      actionLink.href = url;
      actionLink.textContent = text || 'Add to cart';
      actionLink.style.display = '';
    } else {
      actionLink.style.display = 'none';
    }
  }

  function setSocialLink(el, url) {
    if (!el) {
      return;
    }
    if (url) {
      el.href = url;
      el.style.display = '';
    } else {
      el.style.display = 'none';
    }
  }

  function buildPillsHtml(data) {
    const pills = [];

    // Add tier pill
    if (data.tier_label) {
      pills.push(`<span class="np-pill np-pill--tier np-pill--${data.tier}">${data.tier_label}</span>`);
    }

    // Add category pills (max 3)
    if (data.categories && data.categories.length > 0) {
      data.categories.slice(0, 3).forEach(cat => {
        pills.push(`<span class="np-pill np-pill--category np-pill--${cat.slug}">${cat.label}</span>`);
      });
    }

    return pills.length > 0 ? `<div class="np-pills">${pills.join('')}</div>` : '';
  }

  function buildModalContent(data) {
    const parts = [];

    // Add pills at the top
    const pillsHtml = buildPillsHtml(data);
    if (pillsHtml) {
      parts.push(pillsHtml);
    }

    if (data.details) {
      parts.push(`<div class="np-modal__details">${data.details}</div>`);
    }
    return parts.join('');
  }

  function applyDataToModal(data) {
    const html = buildModalContent(data || {});
    openModal(html || '<p>Unable to load details.</p>', data.title || 'Node details');
    if (priceEl) {
      if (data.price_html) {
        const plainPrice = data.price_html.replace(/<[^>]+>/g, '').toLowerCase();
        const hasPeriod = plainPrice.includes('/') || plainPrice.includes('month');
        if (hasPeriod) {
          priceEl.innerHTML = data.price_html;
        } else {
          priceEl.innerHTML = `<span class="np-modal__price-amount">${data.price_html}</span><span class="np-modal__price-period">/ month</span>`;
        }
      } else {
        priceEl.innerHTML = '';
      }
    }
    if (logoEl && data.image) {
      logoEl.src = data.image;
      logoEl.classList.add('is-visible');
    } else if (logoEl) {
      logoEl.classList.remove('is-visible');
    }
    setSocialLink(discordLink, data.discord_url);
    setSocialLink(telegramLink, data.telegram_url);
    setSocialLink(twitterLink, data.twitter_url);
    setSocialLink(guideLink, data.guide_url);
    setActionLink(data.add_to_cart_url, data.add_to_cart_text);
  }

  function getFallbackData(target) {
    if (!target) {
      return null;
    }
    const titleEl = target.querySelector('.node_item_title, .node-card__title');
    const detailsEl = target.querySelector('.node_item_description, .node-card__description');
    const priceElLocal = target.querySelector('.node_item_price, .node-card__price');
    const imageEl = target.querySelector('.node_item_title img, .node-card__image img, img');

    const title = titleEl ? titleEl.textContent.trim() : '';
    const details = detailsEl ? detailsEl.innerHTML : '';
    const priceHtml = priceElLocal ? priceElLocal.innerHTML : '';
    const image = imageEl ? imageEl.getAttribute('src') : '';

    if (!title && !details && !priceHtml && !image) {
      return null;
    }

    return {
      title,
      details,
      price_html: priceHtml,
      image,
    };
  }

  function openByProductId(productId, fallbackData) {
    if (!productId || !window.npNodeDetails) {
      if (fallbackData) {
        applyDataToModal(fallbackData);
      }
      return;
    }

    const payload = new URLSearchParams();
    payload.append('action', 'np_node_details');
    payload.append('nonce', window.npNodeDetails.nonce || '');
    payload.append('product_id', productId);

    openModal('<p>Loading...</p>', 'Node details');
    setActionLink('', '');

    fetch(window.npNodeDetails.ajax_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: payload.toString(),
    })
      .then(response => response.text().then(text => {
        try {
          return JSON.parse(text);
        } catch (error) {
          throw new Error(text || 'invalid_json');
        }
      }))
      .then(result => {
        if (!result || !result.success) {
          if (fallbackData) {
            applyDataToModal(fallbackData);
          } else {
            openModal('<p>Unable to load details.</p>', 'Node details');
          }
          return;
        }
        applyDataToModal(result.data || {});
      })
      .catch(() => {
        if (fallbackData) {
          applyDataToModal(fallbackData);
        } else {
          openModal('<p>Unable to load details.</p>', 'Node details');
        }
      });
  }

  closeButtons.forEach(button => {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      closeModal();
    });
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
    if (e.key !== 'Tab' || !modal.classList.contains('is-open')) {
      return;
    }

    const focusables = modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusables.length) {
      return;
    }
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  document.addEventListener('click', function (e) {
    const nodeCard = e.target.closest('.node-card');
    const nodeItem = e.target.closest('.node_item');
    const target = nodeCard || nodeItem;
    if (!target) {
      return;
    }

    if (e.target.closest('.node-card__checkbox, .node-card__help, .node_item_action_btns, .custom_quantity, .minus, .plus, .quantity-btn, .btn, .btn_2, .node_quantity, .add-to-cart-btn, button, a, input')) {
      return;
    }

    e.preventDefault();
    lastTrigger = target;
    const productId = target.getAttribute('data-product-id');
    const fallbackData = getFallbackData(target);
    openByProductId(productId, fallbackData);
  });

// Handle modal add-to-cart button AJAX  if (actionLink) {    actionLink.addEventListener("click", function (e) {      const url = this.getAttribute("href");      if (url && url.includes("add-to-cart=")) {        e.preventDefault();        const match = url.match(/add-to-cart=(d+)/);        if (match) {          const productId = match[1];          const formData = new FormData();          formData.append("product_id", productId);          formData.append("quantity", 1);          fetch("/?wc-ajax=add_to_cart", {            method: "POST",            body: formData          })          .then(response => response.json())          .then(data => {            if (data.error) {              alert(data.error);            } else {              closeModal();              document.body.dispatchEvent(new Event("wc_fragment_refresh"));              if (typeof custom_cart_ajax !== "undefined") {                window.location.href = custom_cart_ajax.cart_url;              }            }          })          .catch(error => {            console.error("Add to cart error:", error);            alert("Failed to add product to cart");          });        }      }    });  }
  const params = new URLSearchParams(window.location.search);
  const productParam = params.get('product');
  if (productParam) {
    openByProductId(productParam);
  }
});


document.addEventListener('DOMContentLoaded', function () {
  const menuLinks = document.querySelectorAll('.menu a[href^="#"]');
  const selectNode = document.querySelectorAll('.select_node');

  menuLinks.forEach(link => {
    link.addEventListener('click', function (e) {
      e.preventDefault();

      document.querySelectorAll('.hidden_menu_btn.opened, .menu_and_languages.opened').forEach(element => {
        element.classList.remove('opened');
      });

      const targetId = this.getAttribute('href').substring(1);
      const targetSection = document.getElementById(targetId);

      if (targetSection) {
        const targetPosition = targetSection.offsetTop - 50;

        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }
    });
  });

  selectNode.forEach(btn => {
    btn.addEventListener('click', function (e) {
      e.preventDefault();

      const targetId = "nodes";
      const targetSection = document.getElementById(targetId);

      if (targetSection) {
        const targetPosition = targetSection.offsetTop - 50;

        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      } else {
        window.location.href = '/#nodes';
      }
    });
  });

  const connectNow = document.querySelectorAll('.red_button_link');
  connectNow.forEach(btn => {
    btn.addEventListener('click', function(e) {
        const href = this.getAttribute('href');

        if (href && href.startsWith('#')) {
          e.preventDefault();

          const targetId = href.substring(1);
          const targetElement = document.getElementById(targetId);

          if (targetElement) {
            targetElement.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        }
      });
  });


});


document.addEventListener('DOMContentLoaded', function () {
  const hiddenMenuBtn = document.querySelector('.hidden_menu_btn');
  const mobileMenu = document.querySelector('.menu_and_languages');

  if (hiddenMenuBtn) {
    hiddenMenuBtn.addEventListener('click', function () {
      this.classList.toggle('opened');

      if (mobileMenu) {
        mobileMenu.classList.toggle('opened');
      }
    });
  }
});

document.addEventListener('DOMContentLoaded', function () {
  window.addEventListener('resize', function () {
    document.querySelectorAll('.hidden_menu_btn.opened, .menu_and_languages.opened').forEach(element => {
      element.classList.remove('opened');
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  document.addEventListener('click', function (e) {
    if (e.target.tagName === 'A' && e.target.classList.contains('disabled')) {
      e.preventDefault();
      e.stopPropagation();
    }
  });
});

(function () {
  function toggleNodeHelp(button) {
    const targetId = button.getAttribute('aria-controls');
    const info = targetId ? document.getElementById(targetId) : null;
    if (!info) {
      return;
    }

    const isHidden = info.hasAttribute('hidden');
    if (isHidden) {
      info.removeAttribute('hidden');
    } else {
      info.setAttribute('hidden', '');
    }

    button.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
  }

  document.addEventListener('click', function (e) {
    const button = e.target.closest('.node-card__help');
    if (!button) {
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    toggleNodeHelp(button);
  }, true);

  document.addEventListener('keydown', function (e) {
    const button = e.target.closest('.node-card__help');
    if (!button) {
      return;
    }

    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      e.stopPropagation();
      toggleNodeHelp(button);
    }
  }, true);
})();

(function () {
  const menu = document.querySelector('.mock-nodes-menu');
  if (!menu) {
    return;
  }

  const toggle = menu.querySelector('.mock-nodes-menu__toggle');
  if (!toggle) {
    return;
  }

  toggle.addEventListener('click', function (e) {
    e.preventDefault();
    menu.classList.toggle('is-open');
  });

  document.addEventListener('click', function (e) {
    if (!menu.classList.contains('is-open')) {
      return;
    }

    if (!menu.contains(e.target)) {
      menu.classList.remove('is-open');
    }
  });
})();

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.np-settings__toggle').forEach(toggle => {
    toggle.addEventListener('click', function () {
      const targetId = this.getAttribute('data-toggle');
      const input = targetId ? document.getElementById(targetId) : null;
      if (!input) {
        return;
      }

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.querySelector('.np-settings__toggle-security');
  const section = document.getElementById('np-security');
  if (!toggle || !section) {
    return;
  }

  section.classList.remove('is-open');
  toggle.setAttribute('aria-expanded', 'false');

  toggle.addEventListener('click', function () {
    const isOpen = section.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  });
});

document.addEventListener('DOMContentLoaded', function() {
  const redirectElements = document.querySelectorAll('.redirect_to');

  redirectElements.forEach(element => {
    element.addEventListener('click', function() {
      const redirectUrl = this.getAttribute('data-redirect_to');

      if (redirectUrl) {
        window.location.href = redirectUrl;
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  const params = new URLSearchParams(window.location.search);
  if (params.get('status') !== 'overdue') {
    return;
  }

  const overdueCard = document.querySelector('.node-card--status-overdue');
  if (!overdueCard) {
    return;
  }

  overdueCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
