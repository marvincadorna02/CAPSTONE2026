<!-- PAGE LOADER (Fix It Davao — Wrench & Gear) — shared across post-login pages -->
<style>
#pageLoader{
  position:fixed;inset:0;z-index:99999;
  background:#020617;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:26px;overflow:hidden;
  transition:opacity 0.5s ease, visibility 0.5s ease;
}
#pageLoader.loaded{ opacity:0;visibility:hidden;pointer-events:none; }
#pageLoader .loader-bg-grid{
  position:absolute;inset:0;
  background-image:linear-gradient(rgba(245,158,11,0.04) 1px,transparent 1px),linear-gradient(90deg,rgba(245,158,11,0.04) 1px,transparent 1px);
  background-size:50px 50px;
}
#pageLoader .loader-glow{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
  width:420px;height:420px;
  background:radial-gradient(circle,rgba(245,158,11,0.16) 0%,transparent 70%);
  pointer-events:none;
}
#pageLoader .loader-rig{ position:relative;width:120px;height:120px;display:flex;align-items:center;justify-content:center; }
#pageLoader .loader-gear{ position:absolute;width:100px;height:100px;animation:gearSpin 3s linear infinite; }
#pageLoader .loader-gear svg{width:100%;height:100%;}
#pageLoader .loader-gear path,#pageLoader .loader-gear circle{fill:none;stroke:rgba(245,158,11,0.35);stroke-width:2.5;}
@keyframes gearSpin{to{transform:rotate(360deg);}}
#pageLoader .loader-wrench{ position:relative;width:46px;height:46px;z-index:2;animation:wrenchTurn 1.6s ease-in-out infinite;transform-origin:70% 70%;filter:drop-shadow(0 4px 12px rgba(245,158,11,0.5)); }
#pageLoader .loader-wrench svg{width:100%;height:100%;}
@keyframes wrenchTurn{ 0%,100%{transform:rotate(-18deg);} 50%{transform:rotate(18deg);} }
#pageLoader .loader-sparks{ position:absolute;width:8px;height:8px;border-radius:50%;background:#fbbf24;opacity:0; }
#pageLoader .loader-sparks:nth-child(1){top:20%;left:75%;animation:sparkPop 1.6s ease-in-out infinite 0.1s;}
#pageLoader .loader-sparks:nth-child(2){top:70%;left:80%;animation:sparkPop 1.6s ease-in-out infinite 0.5s;}
#pageLoader .loader-sparks:nth-child(3){top:75%;left:20%;animation:sparkPop 1.6s ease-in-out infinite 0.9s;}
@keyframes sparkPop{ 0%,100%{opacity:0;transform:scale(0.4);} 50%{opacity:1;transform:scale(1.2);} }
#pageLoader .loader-brand{ font-size:17px;font-weight:800;letter-spacing:1px;color:#fff;display:flex;align-items:center;gap:6px;font-family:"Outfit",sans-serif; }
#pageLoader .loader-brand span{color:#f59e0b;}
#pageLoader .loader-bar-track{ width:180px;height:4px;border-radius:99px;background:rgba(255,255,255,0.08);overflow:hidden; }
#pageLoader .loader-bar-fill{ width:40%;height:100%;border-radius:99px;background:linear-gradient(90deg,#f59e0b,#fbbf24);animation:barSlide 1.3s ease-in-out infinite; }
@keyframes barSlide{ 0%{transform:translateX(-100%);} 100%{transform:translateX(280%);} }
#pageLoader .loader-text{ font-size:12px;font-weight:600;letter-spacing:1.5px;color:rgba(255,255,255,0.4);text-transform:uppercase;font-family:'Space Mono',monospace; }
</style>

<div id="pageLoader">
  <div class="loader-bg-grid"></div>
  <div class="loader-glow"></div>

  <div class="loader-rig">
    <div class="loader-gear">
      <svg viewBox="0 0 100 100">
        <circle cx="50" cy="50" r="34"/>
        <path d="M50 8 L54 20 L46 20 Z M50 92 L54 80 L46 80 Z
                 M92 50 L80 54 L80 46 Z M8 50 L20 54 L20 46 Z
                 M78.5 21.5 L69.5 27.5 L74 20 Z
                 M21.5 78.5 L30.5 72.5 L26 80 Z
                 M78.5 78.5 L72.5 69.5 L80 74 Z
                 M21.5 21.5 L27.5 30.5 L20 26 Z"/>
      </svg>
    </div>
    <div class="loader-wrench">
      <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
      </svg>
    </div>
    <div class="loader-sparks"></div>
    <div class="loader-sparks"></div>
    <div class="loader-sparks"></div>
  </div>

  <div class="loader-brand">Fix It <span>Davao</span></div>
  <div class="loader-bar-track"><div class="loader-bar-fill"></div></div>
  <div class="loader-text">Getting things ready...</div>
</div>

<script>
  window.addEventListener('load', function () {
    var loader = document.getElementById('pageLoader');
    if (loader) setTimeout(function () { loader.classList.add('loaded'); }, 300);
  });
</script>
