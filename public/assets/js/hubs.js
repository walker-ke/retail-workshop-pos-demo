(function(){
 'use strict';
 var source=document.getElementById('hubCatalog');if(!source||!window.ProductSearch)return;
 var data=JSON.parse(source.textContent),products=data.products.map(window.ProductSearch.prepare);
 document.querySelectorAll('.hub-product-picker').forEach(function(picker){
  var input=picker.querySelector('input[type=search]'),id=picker.querySelector('input[type=hidden]'),results=picker.querySelector('.hub-product-results'),status=picker.querySelector('.hub-product-status'),frame,page=0;
  function render(){
   results.replaceChildren();id.value='';input.setCustomValidity('Choose an item from the search results.');
   var stage=picker.closest('#workshopProduction')?.querySelector('[name=stage]');
   var catalog=stage?.value==='uppers'&&data.workshopUppers?data.workshopUppers.map(window.ProductSearch.prepare).concat(products.filter(function(p){return !data.workshopUppers.some(function(u){return String(u.id)===String(p.id);});})):products;
   var garment=stage?.value==='tailor'?picker.closest('form').querySelector('[name=garment_type]')?.value:'';
   if(garment&&window.WorkshopProducts)catalog=catalog.filter(function(p){return window.WorkshopProducts.matches(p,garment);});
   var term=input.value.trim(),matches=term?window.ProductSearch.rankedProducts(catalog,term):[];
   results.hidden=!matches.length;input.setAttribute('aria-expanded',String(matches.length>0));
   status.textContent=term?(matches.length?matches.length+' matches — choose an item.':'No matching products for this garment type.'):'Search and select an item.';
   var pages=Math.max(1,Math.ceil(matches.length/20));page=Math.min(page,pages-1);
   matches.slice(page*20,(page+1)*20).forEach(function(product){
    var button=document.createElement('button');button.type='button';button.textContent=product.name+(data.showBalances===false?'':' · '+Number(data.balances[product.id]||0)+' in this hub');
    button.addEventListener('click',function(){id.value=product.id;input.value=product.name;input.setCustomValidity('');results.hidden=true;input.setAttribute('aria-expanded','false');status.textContent=data.showBalances===false?'Product selected.':Number(data.balances[product.id]||0)+' currently in this hub.';picker.dispatchEvent(new CustomEvent('productselected',{bubbles:true,detail:{id:product.id}}));input.focus();});results.appendChild(button);
   });
   if(matches.length>20){
    var nav=document.createElement('div');nav.className='d-flex';
    [['Previous',-1],['Next',1]].forEach(function(option){var button=document.createElement('button');button.type='button';button.textContent=option[0];button.disabled=option[1]<0?page===0:page>=pages-1;button.addEventListener('click',function(){page+=option[1];render();});nav.appendChild(button);});results.appendChild(nav);
    status.textContent+=' Page '+(page+1)+' of '+pages+'.';
   }
  }
  input.addEventListener('input',function(){page=0;id.value='';input.setCustomValidity('Choose an item from the search results.');cancelAnimationFrame(frame);frame=requestAnimationFrame(render);});
  input.addEventListener('keydown',function(event){if(event.key==='ArrowDown'&&!results.hidden){event.preventDefault();var first=results.querySelector('button');if(first)first.focus();}if(event.key==='Escape'){results.hidden=true;input.setAttribute('aria-expanded','false');}});
  picker.closest('form').addEventListener('submit',function(event){if(!id.value){event.preventDefault();input.setCustomValidity('Choose an item from the search results.');input.reportValidity();}});
 });
 document.querySelectorAll('.hub-page form[method=POST],.hub-delivery form[method=POST]').forEach(function(form){form.addEventListener('submit',function(event){setTimeout(function(){if(event.defaultPrevented)return;form.querySelectorAll('button').forEach(function(button){button.dataset.hubOriginal=button.textContent;button.dataset.hubPending='1';button.disabled=true;});},0);});});
})();
