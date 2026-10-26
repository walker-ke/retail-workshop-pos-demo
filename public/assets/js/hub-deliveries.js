(function(){
 'use strict';
 document.querySelectorAll('.hub-delivery form').forEach(function(form){
  var count=form.querySelector('input[name=quantity]'),note=form.querySelector('input[name=note]');
  if(count){
   var message=document.createElement('p');message.className='text-muted';message.setAttribute('aria-live','polite');count.closest('label').after(message);
   function update(){var missing=Number(count.max)-Number(count.value);if(count.value!==''&&(!Number.isInteger(Number(count.value))||Number(count.value)<0||missing<0)){message.textContent='Enter a whole quantity between 0 and '+count.max+'.';return;}note.required=count.value!==''&&missing>0;message.textContent=count.value===''?'':missing>0?missing+' items will be flagged for the owner. Only '+count.value+' will enter shop stock.':'All sent items will enter shop stock.';}
   count.addEventListener('input',update);update();
  }
  form.addEventListener('submit',function(event){if(event.defaultPrevented)return;var button=form.querySelector('button');if(button){button.dataset.hubOriginal=button.dataset.hubOriginal||button.textContent;button.dataset.hubPending='1';button.disabled=true;button.textContent='Saving…';}});
 });
 window.addEventListener('pageshow',function(){document.querySelectorAll('button[data-hub-pending]').forEach(function(button){button.disabled=false;button.textContent=button.dataset.hubOriginal;delete button.dataset.hubPending;});});
})();
