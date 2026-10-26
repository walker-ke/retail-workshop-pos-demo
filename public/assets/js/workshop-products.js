(function(root,factory){if(typeof module==='object'&&module.exports)module.exports=factory();else root.WorkshopProducts=factory();})(typeof window==='undefined'?this:window,function(){
 'use strict';
 function matches(product,type){
  const text=((product.name||'')+' '+(product.brand||'')).toLowerCase().replace(/[^a-z0-9]/g,'');
  const long=/longsleeve|lonsleeve/.test(text),sleeveless=/sleeveless/.test(text),tee=/tshirt|teeshirt/.test(text),shirt=/shirt/.test(text),sweater=/sweater/.test(text);
  switch(type){
   case 'longsleeve_shirt':return long&&shirt&&!tee&&!sweater;
   case 'shirt':return shirt&&!long&&!tee&&!sweater;
   case 'tshirt':return tee;
   case 'trouser':return /trouser/.test(text);
   case 'shorts':return /short/.test(text)&&!shirt&&!sweater;
   case 'skirt':return /skirt/.test(text);
   case 'dress':return /dress/.test(text);
   case 'tracksuit':return /tracksuit|tracksuiy/.test(text);
   case 'fleece_jacket':return /fleece|fleec|jumper/.test(text);
   case 'overalls':return /overall|overroll/.test(text);
   case 'dust_coat':return /dustcoat/.test(text);
   case 'blazer':return /blazer/.test(text);
   case 'sweater':return sweater&&!sleeveless;
   case 'sleeveless_sweater':return sweater&&sleeveless;
   case 'apron':return /apron/.test(text);
   case 'pe_kit':return /pekit/.test(text);
   case 'raincoat':return /raincoat/.test(text);
   default:return false;
  }
 }
 return {matches:matches};
});
