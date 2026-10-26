
   <!--<div class="menu-btn">
     <i class="fas fa-bars"></i>
   </div>-->

   <div class="side-bar">
   <div class="brand-mark">
     <span class="brand-mark-icon"><i class="fa-solid fa-shirt"></i></span>
     <span class="brand-mark-text">DEMO RETAIL<br><small>WORKSHOP</small></span>
   </div>
     <div class="menu">
       @if(auth()->user()->hasAccess('dashboard'))
       <div class="item {{Route::is('home') ? 'active' : '' }}"><a href="{{ route('home') }}"><i class="fas fa-desktop"></i>Dashboard</a></div>
       @endif
       @if(auth()->user()->hasAccess('pos'))
       <div class="item"><a href="{{ route('pos') }}"><i class="fa-solid fa-cash-register"></i> POS</a></div>
       @endif
       @if(auth()->user()->hasAccess('view_products'))
       <div class="item {{Route::is('list_product') ? 'active' : '' }}">
         <a href="{{ route('list_product') }}"><i class="fa-solid fa-cart-shopping cus-icon"></i> Products</a>
       </div>
       @endif
       @if(auth()->user()->hasAccess('hubs'))
       <div class="item {{ Route::is('hubs.*') ? 'active' : '' }}">
         <a href="{{ route('hubs.index',request()->only('hub_id')) }}"><i class="fa-solid fa-truck"></i> Hubs</a>
       </div>
       @endif
       @if(auth()->user()->hasAccess('workshop'))
       <div class="item {{ Route::is('workshop.*') ? 'active' : '' }}"><a href="{{ route('workshop.index') }}"><i class="fa-solid fa-shirt"></i> Workshop</a></div>
       @endif
       @if(auth()->user()->hasAccess('stock_transfers'))
       <div class="item {{ Route::is('transfers.*') ? 'active' : '' }}"><a href="{{ route('transfers.index') }}"><i class="fa-solid fa-truck"></i> Stock Requests <span id="transfer-alert-count" class="badge bg-danger" hidden></span></a></div>
       @push('libraries')
       <script>
       (() => {
           const badge = document.getElementById('transfer-alert-count');
           let refreshing = false;
           async function refreshTransferAlerts() {
               if (document.hidden || !badge || refreshing) return;
               refreshing = true;
               const controller = new AbortController();
               const timeout = setTimeout(() => controller.abort(), 10000);
               try {
                   const response = await fetch(@json(route('transfers.alerts')), {headers: {'Accept':'application/json'}, signal:controller.signal});
                   if (!response.ok) return;
                   const data = await response.json();
                   badge.textContent = data.count; badge.hidden = !data.count;
               } catch (_) { /* Keep navigation usable while offline. */ }
               finally { clearTimeout(timeout); refreshing = false; }
           }
           // Spread requests across users instead of a synchronized login burst.
           const pollDelay = 60000 + Math.floor(Math.random() * 15000);
           setTimeout(refreshTransferAlerts, Math.floor(Math.random() * 5000));
           setInterval(refreshTransferAlerts, pollDelay);
       })();
       </script>
       @endpush
       @endif
       @if(auth()->user()->hasAccess('stock'))
       <div class="item {{Route::is(['stock_index', 'stock_history']) ? 'active' : '' }}">
         <a href="{{ route('stock_index') }}"><i class="fa-solid fa-dolly"></i> Stock</a>
       </div>
       @endif
       @if(auth()->user()->hasAccess('shops'))
       <div class="item {{Route::is('shops') ? 'active' : '' }}">
         <a href="{{ route('shops') }}"><i class="fa-solid fa-shop"></i> Shops</a>
       </div>
       @endif
       @if(auth()->user()->hasAccess('view_sales_history'))
       <div class="item  {{Route::is('transaction') ? 'active' : '' }}"><a href="{{ route('transaction') }}"><i class="fa-solid fa-clock-rotate-left"></i> Transaction History</a></div>
       @endif
       @if(auth()->user()->hasAccess('refund'))
       <div class="item {{Route::is('refunds') ? 'active' : '' }}"><a href="{{ route('refunds') }}"><i class="fa-solid fa-rotate-left"></i> Refunds</a></div>
       @endif
       @if(auth()->user()->hasAccess('reporting'))
       <div class="item {{Route::is('report_profit') ? 'active' : '' }}">
         <a href="{{ route('report_profit') }}"><i class="fa-solid fa-chart-line"></i> Reports</a>
       </div>
       @endif
       @if(auth()->user()->hasAccess('users_security'))
       <div class="item">
         <a class="sub-btn {{Route::is(['user', 'user_security']) ? 'active' : '' }}"><i class="fa-solid fa-key"></i>Users &amp; Security<i class="fas fa-angle-right dropdown"></i></a>
         <div class="sub-menu">
           <a href="{{ route('user') }}" class="sub-item {{Route::is('user') ? 'active' : '' }}"><i class="fa-solid fa-user"></i> Users</a>
           <a href="{{ route('user_security') }}" class="sub-item {{Route::is('user_security') ? 'active' : '' }}"><i class="fa-solid fa-shield-halved"></i> Security</a>
         </div>
       </div>
       @endif
       @if(auth()->user()->hasAccess('payment_types'))
       <div class="item {{Route::is('payment_types') ? 'active' : '' }}">
         <a href="{{ route('payment_types') }}"><i class="fa-solid fa-credit-card"></i> Payment Types</a>
       </div>
       @endif
       @if(auth()->user()->hasAccess('backup'))
       <div class="item">
         <a class="sub-btn {{Route::is('backup') ? 'active' : '' }}"><i class="fas fa-cogs"></i>Settings<i class="fas fa-angle-right dropdown"></i></a>
         <div class="sub-menu">
           @if(auth()->user()->isTopAdmin())
           <a href="{{ route('receipt_settings') }}" class="sub-item"><i class="fa-solid fa-receipt"></i> Receipt Settings</a>
           @endif
           <a href="{{ route('backup') }}" class="sub-item {{Route::is('backup') ? 'active' : '' }}"><i class="fa-solid fa-database"></i> Backups & Recovery</a>
         </div>
       </div>
       @endif
     </div>
   </div>
