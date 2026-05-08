// Dev 1, Dev 2, Dev 3: Shared frontend behavior for navigation, dashboards, forms, and mobile support.
const navToggle = document.querySelector("[data-nav-toggle]");
const navMenu = document.querySelector("[data-nav-menu]");
const siteHeader = document.querySelector(".site-header");
const transitionableLinks = Array.from(
  document.querySelectorAll("a[href]"),
).filter((link) => {
  const href = link.getAttribute("href");

  if (
    !href ||
    href.startsWith("#") ||
    href.startsWith("mailto:") ||
    href.startsWith("tel:")
  ) {
    return false;
  }

  if (link.hasAttribute("download") || link.target === "_blank") {
    return false;
  }

  try {
    const url = new URL(link.href, window.location.href);

    return url.origin === window.location.origin;
  } catch {
    return false;
  }
});

// All Devs: Mobile Compatibility - activate the page transition state after the layout is ready.
document.body.classList.add("page-ready");

// All Devs: Shared UI - update header styling when the user scrolls.
if (siteHeader) {
  const syncHeaderScrollState = () => {
    siteHeader.classList.toggle("is-scrolled", window.scrollY > 16);
  };

  window.addEventListener("scroll", syncHeaderScrollState, { passive: true });
  syncHeaderScrollState();
}

// All Devs: Mobile Compatibility - open and close the mobile navigation menu.
if (navToggle && navMenu && siteHeader) {
  const setNavState = (isOpen) => {
    navMenu.classList.toggle("open", isOpen);
    siteHeader.classList.toggle("nav-open", isOpen);
    navToggle.setAttribute("aria-expanded", String(isOpen));
  };

  navToggle.addEventListener("click", () => {
    setNavState(!navMenu.classList.contains("open"));
  });

  document.addEventListener("click", (event) => {
    if (!siteHeader.contains(event.target)) {
      setNavState(false);
    }
  });

  navMenu.addEventListener("click", (event) => {
    if (
      event.target instanceof HTMLElement &&
      event.target.tagName === "A" &&
      window.innerWidth <= 960
    ) {
      setNavState(false);
    }
  });
}

// All Devs: Shared UI - animate internal page navigation links.
transitionableLinks.forEach((link) => {
  link.addEventListener("click", (event) => {
    if (
      event.defaultPrevented ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey ||
      event.button !== 0
    ) {
      return;
    }

    const targetUrl = new URL(link.href, window.location.href);

    if (targetUrl.href === window.location.href) {
      return;
    }

    event.preventDefault();

    document.body.classList.add("page-transitioning");
    link.classList.add("is-activating");

    window.setTimeout(() => {
      window.location.href = targetUrl.href;
    }, 320);
  });
});
