/* ============================================================
   Fix It Davao — Custom Alert / Confirm Modal System
   Replaces native browser alert()/confirm() ("localhost says...")
   with a styled modal that matches the site's dark navy/amber theme.

   Usage:
     alert("message")                → still works, auto-routed to modal
     await customConfirm("message")  → returns Promise<boolean>
   ============================================================ */
(function () {
  if (window.__fixitModalsInstalled) return;
  window.__fixitModalsInstalled = true;

  const STYLE = `
    .fixit-modal-overlay {
      position: fixed; inset: 0; z-index: 99999;
      background: rgba(4, 8, 20, 0.72);
      backdrop-filter: blur(4px);
      display: flex; align-items: center; justify-content: center;
      opacity: 0; visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
      font-family: "Outfit", sans-serif;
      padding: 16px;
    }
    .fixit-modal-overlay.show { opacity: 1; visibility: visible; }
    .fixit-modal-box {
      background: linear-gradient(180deg, #16213a 0%, #0f172a 100%);
      border: 1px solid rgba(245, 158, 11, 0.18);
      border-radius: 16px;
      width: 100%; max-width: 380px;
      box-shadow: 0 24px 60px rgba(0,0,0,0.5);
      transform: translateY(14px) scale(0.97);
      transition: transform 0.22s cubic-bezier(.34,1.56,.64,1);
      overflow: hidden;
    }
    .fixit-modal-overlay.show .fixit-modal-box { transform: translateY(0) scale(1); }
    .fixit-modal-icon {
      width: 52px; height: 52px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 24px auto 4px; font-size: 24px;
    }
    .fixit-modal-icon.info    { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .fixit-modal-icon.confirm { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
    .fixit-modal-icon.danger  { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .fixit-modal-body { padding: 4px 24px 24px; text-align: center; }
    .fixit-modal-msg {
      color: #e2e8f0; font-size: 0.92rem; line-height: 1.5;
      white-space: pre-line; margin: 6px 0 20px;
    }
    .fixit-modal-actions { display: flex; gap: 10px; }
    .fixit-modal-actions button {
      flex: 1; padding: 11px 14px; border-radius: 10px; border: none;
      font-family: "Outfit", sans-serif; font-weight: 700; font-size: 0.86rem;
      cursor: pointer; transition: filter 0.15s ease, transform 0.1s ease;
    }
    .fixit-modal-actions button:active { transform: scale(0.97); }
    .fixit-btn-primary { background: linear-gradient(135deg, #f59e0b, #d97706); color: #1a1206; }
    .fixit-btn-primary:hover { filter: brightness(1.08); }
    .fixit-btn-secondary { background: rgba(148, 163, 184, 0.12); color: #cbd5e1; }
    .fixit-btn-secondary:hover { background: rgba(148, 163, 184, 0.2); }
    .fixit-btn-danger { background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff; }
    .fixit-btn-danger:hover { filter: brightness(1.08); }
  `;

  const styleEl = document.createElement("style");
  styleEl.textContent = STYLE;
  document.head.appendChild(styleEl);

  const overlay = document.createElement("div");
  overlay.className = "fixit-modal-overlay";
  overlay.innerHTML = `
    <div class="fixit-modal-box">
      <div class="fixit-modal-icon"></div>
      <div class="fixit-modal-body">
        <div class="fixit-modal-msg"></div>
        <div class="fixit-modal-actions"></div>
      </div>
    </div>
  `;
  document.addEventListener("DOMContentLoaded", () => document.body.appendChild(overlay));
  // In case script runs after DOMContentLoaded already fired
  if (document.readyState !== "loading") document.body.appendChild(overlay);

  const iconEl    = overlay.querySelector(".fixit-modal-icon");
  const msgEl     = overlay.querySelector(".fixit-modal-msg");
  const actionsEl = overlay.querySelector(".fixit-modal-actions");

  function closeModal() {
    overlay.classList.remove("show");
  }

  function openModal({ message, icon = "info", buttons }) {
    msgEl.textContent = message;
    iconEl.className = "fixit-modal-icon " + icon;
    iconEl.textContent = icon === "danger" ? "!" : (icon === "confirm" ? "?" : "i");
    actionsEl.innerHTML = "";
    buttons.forEach((b) => {
      const btn = document.createElement("button");
      btn.className = b.className;
      btn.textContent = b.label;
      btn.onclick = () => { closeModal(); b.onClick(); };
      actionsEl.appendChild(btn);
    });
    overlay.classList.add("show");
  }

  // ── Override native alert() ──────────────────────────────
  window.alert = function (message) {
    openModal({
      message: String(message),
      icon: "info",
      buttons: [{ label: "OK", className: "fixit-btn-primary", onClick: () => {} }],
    });
  };

  // ── New promise-based confirm ────────────────────────────
  window.customConfirm = function (message, opts = {}) {
    return new Promise((resolve) => {
      openModal({
        message: String(message),
        icon: opts.danger ? "danger" : "confirm",
        buttons: [
          {
            label: opts.cancelLabel || "Cancel",
            className: "fixit-btn-secondary",
            onClick: () => resolve(false),
          },
          {
            label: opts.confirmLabel || "Yes",
            className: opts.danger ? "fixit-btn-danger" : "fixit-btn-primary",
            onClick: () => resolve(true),
          },
        ],
      });
    });
  };

  // Close on backdrop click (acts as Cancel)
  overlay.addEventListener("click", (e) => {
    if (e.target === overlay) closeModal();
  });
})();
