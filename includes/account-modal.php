<?php
// ── Shared Account Settings modal (profile + password) ──
// Self-contained: pulls current account values from DB using the session.
// Requires: an active session with user_id. Reuses each page's existing
// .modal-overlay / .modal-box / .visible CSS.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$acctName    = $_SESSION['name']  ?? '';
$acctEmail   = $_SESSION['email'] ?? '';
$acctContact = '';
if (!empty($_SESSION['user_id'])) {
    $__c = @new mysqli("localhost", "root", "", "fixitdavao");
    if ($__c && !$__c->connect_error) {
        $__s = $__c->prepare("SELECT name, email, contact_number FROM users WHERE id = ?");
        $__uid = (int) $_SESSION['user_id'];
        $__s->bind_param("i", $__uid);
        $__s->execute();
        if ($__r = $__s->get_result()->fetch_assoc()) {
            $acctName    = $__r['name']  ?: $acctName;
            $acctEmail   = $__r['email'] ?: $acctEmail;
            $acctContact = $__r['contact_number'] ?? '';
        }
        $__s->close();
        $__c->close();
    }
}
?>
<style>
  .acct-tab { flex:1; padding:9px; background:#f1f5f9; border:none; border-radius:10px 10px 0 0; font-size:.8rem; font-weight:700; color:#64748b; cursor:pointer; font-family:'Outfit',sans-serif; }
  .acct-tab-active { background:#fff; color:#0f172a; box-shadow:inset 0 -2px 0 #f59e0b; }
  .acct-field { margin-bottom:14px; }
  .acct-field label { display:block; font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
  .acct-field input { width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:10px; font-size:.88rem; font-family:'Outfit',sans-serif; box-sizing:border-box; }
  .acct-field input:focus { outline:none; border-color:#f59e0b; box-shadow:0 0 0 3px rgba(245,158,11,.15); }
  .acct-msg { display:none; padding:9px 12px; border-radius:8px; font-size:.78rem; font-weight:600; margin-bottom:12px; }
  .acct-submit { width:100%; padding:11px; background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; border:none; border-radius:10px; font-size:.85rem; font-weight:700; font-family:'Outfit',sans-serif; cursor:pointer; }
  .acct-submit:disabled { opacity:.6; cursor:not-allowed; }
</style>

<div class="modal-overlay" id="accountModal">
  <div class="modal-box" style="max-width:420px;padding:0;overflow:hidden;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #e2e8f0;">
      <span style="font-size:1.05rem;font-weight:800;color:#0f172a;font-family:'Outfit',sans-serif;">Account Settings</span>
      <button onclick="closeAccountModal()" style="background:#f1f5f9;border:none;width:28px;height:28px;border-radius:8px;cursor:pointer;font-size:14px;color:#64748b;">✕</button>
    </div>
    <div style="display:flex;gap:6px;padding:12px 22px 0;">
      <button id="acctTabProfile" class="acct-tab acct-tab-active" onclick="acctSwitch('profile')">Edit Profile</button>
      <button id="acctTabPass" class="acct-tab" onclick="acctSwitch('password')">Change Password</button>
    </div>
    <form id="acctProfileForm" onsubmit="return saveProfile(event)" style="padding:18px 22px 22px;">
      <div class="acct-field"><label>Full Name</label><input type="text" id="acctName" required /></div>
      <div class="acct-field"><label>Email</label><input type="email" id="acctEmail" required /></div>
      <div class="acct-field"><label>Contact Number</label><input type="text" id="acctContact" /></div>
      <div id="acctProfileMsg" class="acct-msg"></div>
      <button type="submit" class="acct-submit">Save Changes</button>
    </form>
    <form id="acctPassForm" onsubmit="return savePassword(event)" style="padding:18px 22px 22px;display:none;">
      <div class="acct-field"><label>Current Password</label><input type="password" id="acctCurrent" required /></div>
      <div class="acct-field"><label>New Password</label><input type="password" id="acctNew" required /></div>
      <div class="acct-field"><label>Confirm New Password</label><input type="password" id="acctConfirm" required /></div>
      <div id="acctPassMsg" class="acct-msg"></div>
      <button type="submit" class="acct-submit">Update Password</button>
    </form>
  </div>
</div>

<script>
(function(){
  const CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
  const INIT = { name: <?php echo json_encode($acctName); ?>, email: <?php echo json_encode($acctEmail); ?>, contact: <?php echo json_encode($acctContact); ?> };
  function acctMsg(id, text, ok){ const el=document.getElementById(id); if(!text){el.style.display='none';return;} el.style.display='block'; el.textContent=text; el.style.background=ok?'#d1fae5':'#fee2e2'; el.style.color=ok?'#065f46':'#991b1b'; }
  window.acctSwitch = function(which){ const p=which==='profile'; document.getElementById('acctProfileForm').style.display=p?'block':'none'; document.getElementById('acctPassForm').style.display=p?'none':'block'; document.getElementById('acctTabProfile').classList.toggle('acct-tab-active',p); document.getElementById('acctTabPass').classList.toggle('acct-tab-active',!p); };
  window.openAccountModal = function(){ document.getElementById('acctName').value=INIT.name||''; document.getElementById('acctEmail').value=INIT.email||''; document.getElementById('acctContact').value=INIT.contact||''; document.getElementById('acctCurrent').value=''; document.getElementById('acctNew').value=''; document.getElementById('acctConfirm').value=''; acctMsg('acctProfileMsg',''); acctMsg('acctPassMsg',''); acctSwitch('profile'); document.getElementById('accountModal').classList.add('visible'); };
  window.closeAccountModal = function(){ document.getElementById('accountModal').classList.remove('visible'); };
  async function post(payload){ const res=await fetch('../api/update_account.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({csrf_token:CSRF},payload))}); return res.json(); }
  window.saveProfile = async function(e){ e.preventDefault(); const btn=e.target.querySelector('.acct-submit'); btn.disabled=true; try{ const d=await post({action:'update_profile',name:document.getElementById('acctName').value.trim(),email:document.getElementById('acctEmail').value.trim(),contact_number:document.getElementById('acctContact').value.trim()}); acctMsg('acctProfileMsg',d.message||d.error,!!d.success); if(d.success){INIT.name=d.name;INIT.email=d.email;document.querySelectorAll('[data-acct-name]').forEach(el=>el.textContent=d.name);} }catch(err){ acctMsg('acctProfileMsg','Network error. Try again.',false); } btn.disabled=false; return false; };
  window.savePassword = async function(e){ e.preventDefault(); const btn=e.target.querySelector('.acct-submit'); btn.disabled=true; try{ const d=await post({action:'change_password',current_password:document.getElementById('acctCurrent').value,new_password:document.getElementById('acctNew').value,confirm_password:document.getElementById('acctConfirm').value}); acctMsg('acctPassMsg',d.message||d.error,!!d.success); if(d.success){document.getElementById('acctCurrent').value='';document.getElementById('acctNew').value='';document.getElementById('acctConfirm').value='';} }catch(err){ acctMsg('acctPassMsg','Network error. Try again.',false); } btn.disabled=false; return false; };
  const ov=document.getElementById('accountModal'); if(ov) ov.addEventListener('click',function(e){ if(e.target===this) closeAccountModal(); });
})();
</script>
