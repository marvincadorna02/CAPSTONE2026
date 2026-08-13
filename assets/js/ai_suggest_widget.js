/**
 * ai_suggest_widget.js
 * Place in: C:\XAMPP\htdocs\FIXITDAVAO\assets\js\ai_suggest_widget.js
 *
 * Usage - add this to any booking/quote form:
 *
 *   <script src="ai_suggest_widget.js"></script>
 *   <script>
 *     initAISuggest({
 *       descriptionInputId: 'issue_description',  // your textarea/input ID
 *       deviceTypeInputId:  'device_type',         // your device type select/input ID
 *       targetContainerId:  'ai_suggestions_box',  // div where suggestions appear
 *       serviceInputId:     'service_id',          // hidden or select input to auto-fill
 *       customerId:         <?= $_SESSION['user_id'] ?? 0 ?>  // pass 0 if admin side
 *     });
 *   </script>
 */

function initAISuggest(options) {
  const {
    descriptionInputId,
    deviceTypeInputId,
    targetContainerId,
    serviceInputId,
    customerId = 0,
    debounceMs = 800,
  } = options;

  const descInput    = document.getElementById(descriptionInputId);
  const deviceInput  = document.getElementById(deviceTypeInputId);
  const container    = document.getElementById(targetContainerId);
  const serviceInput = document.getElementById(serviceInputId);

  if (!descInput || !container) {
    console.warn('[AI Suggest] Missing required elements.');
    return;
  }

  // --- Inject styles ---
  const style = document.createElement('style');
  style.textContent = `
    #${targetContainerId} {
      margin-top: 10px;
    }
    .ai-suggest-label {
      font-size: 12px;
      color: #6b7280;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .ai-suggest-label .ai-icon {
      background: linear-gradient(135deg, #6366f1, #8b5cf6);
      color: white;
      font-size: 10px;
      padding: 2px 6px;
      border-radius: 20px;
      font-weight: 600;
      letter-spacing: 0.5px;
    }
    .ai-suggest-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }
    .ai-chip {
      background: #f0f4ff;
      border: 1.5px solid #c7d2fe;
      color: #3730a3;
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      cursor: pointer;
      transition: all 0.2s ease;
      font-family: inherit;
    }
    .ai-chip:hover {
      background: #6366f1;
      color: white;
      border-color: #6366f1;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(99,102,241,0.3);
    }
    .ai-chip.selected {
      background: #6366f1;
      color: white;
      border-color: #6366f1;
    }
    .ai-chip-reason {
      font-size: 11px;
      opacity: 0.75;
      display: block;
      margin-top: 2px;
    }
    .ai-loading {
      font-size: 13px;
      color: #9ca3af;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .ai-loading-dots span {
      animation: ai-bounce 1.2s infinite;
      display: inline-block;
    }
    .ai-loading-dots span:nth-child(2) { animation-delay: 0.2s; }
    .ai-loading-dots span:nth-child(3) { animation-delay: 0.4s; }
    @keyframes ai-bounce {
      0%, 80%, 100% { transform: translateY(0); }
      40% { transform: translateY(-4px); }
    }
    .ai-error {
      font-size: 12px;
      color: #ef4444;
    }
  `;
  document.head.appendChild(style);

  // --- Debounce helper ---
  let debounceTimer = null;
  function debounce(fn, delay) {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(fn, delay);
  }

  // --- Render loading ---
  function showLoading() {
    container.innerHTML = `
      <div class="ai-loading">
        <span class="ai-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:white;font-size:10px;padding:2px 6px;border-radius:20px;font-weight:600;">AI</span>
        Suggesting services
        <span class="ai-loading-dots">
          <span>.</span><span>.</span><span>.</span>
        </span>
      </div>`;
  }

  // --- Render chips ---
  function showSuggestions(suggestions) {
    if (!suggestions || suggestions.length === 0) {
      container.innerHTML = `<div class="ai-suggest-label"><span class="ai-icon">AI</span> No matching services found.</div>`;
      return;
    }

    let html = `<div class="ai-suggest-label"><span class="ai-icon">AI</span> Suggested services — click to select:</div>`;
    html += `<div class="ai-suggest-chips">`;
    suggestions.forEach(svc => {
      html += `
        <button type="button" class="ai-chip" data-id="${svc.id}" data-name="${svc.service_name}" title="${svc.reason || ''}">
          ${svc.service_name}
          ${svc.reason ? `<span class="ai-chip-reason">${svc.reason}</span>` : ''}
        </button>`;
    });
    html += `</div>`;
    container.innerHTML = html;

    // Chip click handler
    container.querySelectorAll('.ai-chip').forEach(chip => {
      chip.addEventListener('click', () => {
        // Remove selected from all
        container.querySelectorAll('.ai-chip').forEach(c => c.classList.remove('selected'));
        chip.classList.add('selected');

        // Auto-fill service input if provided
        if (serviceInput) {
          serviceInput.value = chip.dataset.id;
          // Trigger change event so other listeners know
          serviceInput.dispatchEvent(new Event('change'));
        }

        // Optional: dispatch custom event
        container.dispatchEvent(new CustomEvent('ai-service-selected', {
          detail: { id: chip.dataset.id, name: chip.dataset.name },
          bubbles: true
        }));
      });
    });
  }

  // --- Render error ---
  function showError(msg) {
    container.innerHTML = `<div class="ai-error">⚠️ ${msg}</div>`;
  }

  // --- Fetch suggestions ---
  async function fetchSuggestions() {
    const description = descInput.value.trim();
    const deviceType  = deviceInput ? deviceInput.value.trim() : '';

    if (description.length < 10) {
      container.innerHTML = '';
      return;
    }

    showLoading();

    try {
      const res = await fetch('../api/ai_suggest.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
       body: JSON.stringify({
  problem_description: description,
  device_type: deviceType,
  customer_id: customerId,
  shop_id: options.shopId ?? 0,
}),
      });

      const data = await res.json();

      if (data.success) {
        showSuggestions(data.suggestions);
      } else {
        showError(data.message || 'Could not get suggestions.');
      }
    } catch (err) {
      showError('Network error. Please try again.');
      console.error('[AI Suggest]', err);
    }
  }

  // --- Attach listeners ---
  descInput.addEventListener('input', () => debounce(fetchSuggestions, debounceMs));
  if (deviceInput) {
    deviceInput.addEventListener('change', () => debounce(fetchSuggestions, debounceMs));
  }
}