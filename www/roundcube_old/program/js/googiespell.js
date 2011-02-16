var AJS={getElement:function(id){
if(typeof (id)=="string"){
return document.getElementById(id);
}else{
return id;
}
},getElements:function(){
var _2=new Array();
for(var i=0;i<arguments.length;i++){
var _4=this.getElement(arguments[i]);
_2.push(_4);
}
return _2;
},getQueryArgument:function(_5){
var _6=window.location.search.substring(1);
var _7=_6.split("&");
for(var i=0;i<_7.length;i++){
var _9=_7[i].split("=");
if(_9[0]==_5){
return _9[1];
}
}
return null;
},isIe:function(){
return (navigator.userAgent.toLowerCase().indexOf("msie")!=-1&&navigator.userAgent.toLowerCase().indexOf("opera")==-1);
},getBody:function(){
return this.getElementsByTagAndClassName("body")[0];
},getElementsByTagAndClassName:function(_a,_b,_c){
var _d=new Array();
if(!this.isDefined(_c)){
_c=document;
}
if(!this.isDefined(_a)){
_a="*";
}
var _e=_c.getElementsByTagName(_a);
var _f=_e.length;
var _10=new RegExp("(^|\\s)"+_b+"(\\s|$)");
for(i=0,j=0;i<_f;i++){
if(_10.test(_e[i].className)||_b==null){
_d[j]=_e[i];
j++;
}
}
return _d;
},appendChildNodes:function(_11){
if(arguments.length>=2){
for(var i=1;i<arguments.length;i++){
var n=arguments[i];
if(typeof (n)=="string"){
n=document.createTextNode(n);
}
if(this.isDefined(n)){
_11.appendChild(n);
}
}
}
return _11;
},replaceChildNodes:function(_14){
var _15;
while((_15=_14.firstChild)){
_14.removeChild(_15);
}
if(arguments.length<2){
return _14;
}else{
return this.appendChildNodes.apply(this,arguments);
}
},insertAfter:function(_16,_17){
_17.parentNode.insertBefore(_16,_17.nextSibling);
},insertBefore:function(_18,_19){
_19.parentNode.insertBefore(_18,_19);
},showElement:function(elm){
elm.style.display="";
},hideElement:function(elm){
elm.style.display="none";
},isElementHidden:function(elm){
return elm.style.visibility=="hidden";
},swapDOM:function(_1d,src){
_1d=this.getElement(_1d);
var _1f=_1d.parentNode;
if(src){
src=this.getElement(src);
_1f.replaceChild(src,_1d);
}else{
_1f.removeChild(_1d);
}
return src;
},removeElement:function(elm){
this.swapDOM(elm,null);
},isDict:function(o){
var _22=String(o);
return _22.indexOf(" Object")!=-1;
},createDOM:function(_23,_24){
var i=0;
elm=document.createElement(_23);
if(this.isDict(_24[i])){
for(k in _24[0]){
if(k=="style"){
elm.style.cssText=_24[0][k];
}else{
if(k=="class"){
elm.className=_24[0][k];
}else{
elm.setAttribute(k,_24[0][k]);
}
}
}
i++;
}
if(_24[0]==null){
i=1;
}
for(i;i<_24.length;i++){
var n=_24[i];
if(this.isDefined(n)){
if(typeof (n)=="string"){
n=document.createTextNode(n);
}
elm.appendChild(n);
}
}
return elm;
},UL:function(){
return this.createDOM.apply(this,["ul",arguments]);
},LI:function(){
return this.createDOM.apply(this,["li",arguments]);
},TD:function(){
return this.createDOM.apply(this,["td",arguments]);
},TR:function(){
return this.createDOM.apply(this,["tr",arguments]);
},TH:function(){
return this.createDOM.apply(this,["th",arguments]);
},TBODY:function(){
return this.createDOM.apply(this,["tbody",arguments]);
},TABLE:function(){
return this.createDOM.apply(this,["table",arguments]);
},INPUT:function(){
return this.createDOM.apply(this,["input",arguments]);
},SPAN:function(){
return this.createDOM.apply(this,["span",arguments]);
},B:function(){
return this.createDOM.apply(this,["b",arguments]);
},A:function(){
return this.createDOM.apply(this,["a",arguments]);
},DIV:function(){
return this.createDOM.apply(this,["div",arguments]);
},IMG:function(){
return this.createDOM.apply(this,["img",arguments]);
},BUTTON:function(){
return this.createDOM.apply(this,["button",arguments]);
},H1:function(){
return this.createDOM.apply(this,["h1",arguments]);
},H2:function(){
return this.createDOM.apply(this,["h2",arguments]);
},H3:function(){
return this.createDOM.apply(this,["h3",arguments]);
},BR:function(){
return this.createDOM.apply(this,["br",arguments]);
},TEXTAREA:function(){
return this.createDOM.apply(this,["textarea",arguments]);
},FORM:function(){
return this.createDOM.apply(this,["form",arguments]);
},P:function(){
return this.createDOM.apply(this,["p",arguments]);
},SELECT:function(){
return this.createDOM.apply(this,["select",arguments]);
},OPTION:function(){
return this.createDOM.apply(this,["option",arguments]);
},TN:function(_27){
return document.createTextNode(_27);
},IFRAME:function(){
return this.createDOM.apply(this,["iframe",arguments]);
},SCRIPT:function(){
return this.createDOM.apply(this,["script",arguments]);
},getXMLHttpRequest:function(){
var _28=[function(){
return new XMLHttpRequest();
},function(){
return new ActiveXObject("Msxml2.XMLHTTP");
},function(){
return new ActiveXObject("Microsoft.XMLHTTP");
},function(){
return new ActiveXObject("Msxml2.XMLHTTP.4.0");
},function(){
throw "Browser does not support XMLHttpRequest";
}];
for(var i=0;i<_28.length;i++){
var _2a=_28[i];
try{
return _2a();
}
catch(e){
}
}
},doSimpleXMLHttpRequest:function(url){
var req=this.getXMLHttpRequest();
req.open("GET",url,true);
return this.sendXMLHttpRequest(req);
},getRequest:function(url,_2e){
var req=this.getXMLHttpRequest();
req.open("POST",url,true);
req.setRequestHeader("Content-type","application/x-www-form-urlencoded");
return this.sendXMLHttpRequest(req);
},sendXMLHttpRequest:function(req,_31){
var d=new AJSDeferred(req);
var _33=function(){
if(req.readyState==4){
try{
var _34=req.status;
}
catch(e){
}
if(_34==200||_34==304||req.responseText==null){
d.callback(req,_31);
}else{
d.errback();
}
}
};
req.onreadystatechange=_33;
return d;
},reprString:function(o){
return ("\""+o.replace(/(["\\])/g,"\\$1")+"\"").replace(/[\f]/g,"\\f").replace(/[\b]/g,"\\b").replace(/[\n]/g,"\\n").replace(/[\t]/g,"\\t").replace(/[\r]/g,"\\r");
},serializeJSON:function(o){
var _37=typeof (o);
if(_37=="undefined"){
return "undefined";
}else{
if(_37=="number"||_37=="boolean"){
return o+"";
}else{
if(o===null){
return "null";
}
}
}
if(_37=="string"){
return this.reprString(o);
}
var me=arguments.callee;
var _39;
if(typeof (o.__json__)=="function"){
_39=o.__json__();
if(o!==_39){
return me(_39);
}
}
if(typeof (o.json)=="function"){
_39=o.json();
if(o!==_39){
return me(_39);
}
}
if(_37!="function"&&typeof (o.length)=="number"){
var res=[];
for(var i=0;i<o.length;i++){
var val=me(o[i]);
if(typeof (val)!="string"){
val="undefined";
}
res.push(val);
}
return "["+res.join(",")+"]";
}
res=[];
for(var k in o){
var _3e;
if(typeof (k)=="number"){
_3e="\""+k+"\"";
}else{
if(typeof (k)=="string"){
_3e=this.reprString(k);
}else{
continue;
}
}
val=me(o[k]);
if(typeof (val)!="string"){
continue;
}
res.push(_3e+":"+val);
}
return "{"+res.join(",")+"}";
},loadJSONDoc:function(url){
var d=this.getRequest(url);
var _41=function(req){
var _43=req.responseText;
return eval("("+_43+")");
};
d.addCallback(_41);
return d;
},keys:function(obj){
var _45=[];
for(var _46 in obj){
_45.push(_46);
}
return _45;
},urlencode:function(str){
return encodeURIComponent(str.toString());
},isDefined:function(o){
return (o!="undefined"&&o!=null);
},isArray:function(obj){
try{
return (typeof (obj.length)=="undefined")?false:true;
}
catch(e){
return false;
}
},isObject:function(obj){
return (obj&&typeof obj=="object");
},exportDOMElements:function(){
UL=this.UL;
LI=this.LI;
TD=this.TD;
TR=this.TR;
TH=this.TH;
TBODY=this.TBODY;
TABLE=this.TABLE;
INPUT=this.INPUT;
SPAN=this.SPAN;
B=this.B;
A=this.A;
DIV=this.DIV;
IMG=this.IMG;
BUTTON=this.BUTTON;
H1=this.H1;
H2=this.H2;
H3=this.H3;
BR=this.BR;
TEXTAREA=this.TEXTAREA;
FORM=this.FORM;
P=this.P;
SELECT=this.SELECT;
OPTION=this.OPTION;
TN=this.TN;
IFRAME=this.IFRAME;
SCRIPT=this.SCRIPT;
},exportToGlobalScope:function(){
getElement=this.getElement;
getQueryArgument=this.getQueryArgument;
isIe=this.isIe;
$=this.getElement;
getElements=this.getElements;
getBody=this.getBody;
getElementsByTagAndClassName=this.getElementsByTagAndClassName;
appendChildNodes=this.appendChildNodes;
ACN=appendChildNodes;
replaceChildNodes=this.replaceChildNodes;
RCN=replaceChildNodes;
insertAfter=this.insertAfter;
insertBefore=this.insertBefore;
showElement=this.showElement;
hideElement=this.hideElement;
isElementHidden=this.isElementHidden;
swapDOM=this.swapDOM;
removeElement=this.removeElement;
isDict=this.isDict;
createDOM=this.createDOM;
this.exportDOMElements();
getXMLHttpRequest=this.getXMLHttpRequest;
doSimpleXMLHttpRequest=this.doSimpleXMLHttpRequest;
getRequest=this.getRequest;
sendXMLHttpRequest=this.sendXMLHttpRequest;
reprString=this.reprString;
serializeJSON=this.serializeJSON;
loadJSONDoc=this.loadJSONDoc;
keys=this.keys;
isDefined=this.isDefined;
isArray=this.isArray;
}};
AJSDeferred=function(req){
this.callbacks=[];
this.req=req;
this.callback=function(res){
while(this.callbacks.length>0){
var fn=this.callbacks.pop();
res=fn(res);
}
};
this.errback=function(e){
alert("Error encountered:\n"+e);
};
this.addErrback=function(fn){
this.errback=fn;
};
this.addCallback=function(fn){
this.callbacks.unshift(fn);
};
this.addCallbacks=function(fn1,fn2){
this.addCallback(fn1);
this.addErrback(fn2);
};
this.sendReq=function(_53){
if(AJS.isObject(_53)){
var _54=[];
for(k in _53){
_54.push(k+"="+AJS.urlencode(_53[k]));
}
_54=_54.join("&");
this.req.send(_54);
}else{
if(AJS.isDefined(_53)){
this.req.send(_53);
}else{
this.req.send("");
}
}
};
};
AJSDeferred.prototype=new AJSDeferred();
var GOOGIE_CUR_LANG="en";
function GoogieSpell(_55,_56){
var _57;
var _58;
_57=getCookie("language");
if(_57!=null){
GOOGIE_CUR_LANG=_57;
}
this.img_dir=_55;
this.server_url=_56;
this.lang_to_word={"da":"Dansk","de":"Deutsch","en":"English","es":"Espa&#241;ol","fr":"Fran&#231;ais","it":"Italiano","nl":"Nederlands","pl":"Polski","pt":"Portugu&#234;s","fi":"Suomi","sv":"Svenska"};
this.langlist_codes=AJS.keys(this.lang_to_word);
this.show_change_lang_pic=true;
this.lang_state_observer=null;
this.spelling_state_observer=null;
this.request=null;
this.error_window=null;
this.language_window=null;
this.edit_layer=null;
this.orginal_text=null;
this.results=null;
this.text_area=null;
this.gselm=null;
this.ta_scroll_top=0;
this.el_scroll_top=0;
this.lang_chck_spell="Check spelling";
this.lang_rsm_edt="Resume editing";
this.lang_close="Close";
this.lang_no_error_found="No spelling errors found";
this.lang_revert="Revert to";
this.show_spell_img=false;
};
GoogieSpell.prototype.setStateChanged=function(_59){
if(this.spelling_state_observer!=null){
this.spelling_state_observer(_59);
}
};
GoogieSpell.item_onmouseover=function(e){
var elm=GoogieSpell.getEventElm(e);
if(elm.className!="googie_list_close"&&elm.className!="googie_list_revert"){
elm.className="googie_list_onhover";
}else{
elm.parentNode.className="googie_list_onhover";
}
};
GoogieSpell.item_onmouseout=function(e){
var elm=GoogieSpell.getEventElm(e);
if(elm.className!="googie_list_close"&&elm.className!="googie_list_revert"){
elm.className="googie_list_onout";
}else{
elm.parentNode.className="googie_list_onout";
}
};
GoogieSpell.prototype.getGoogleUrl=function(){
return this.server_url+GOOGIE_CUR_LANG;
};
GoogieSpell.prototype.spellCheck=function(elm,_5f){
this.ta_scroll_top=this.text_area.scrollTop;
this.appendIndicator(elm);
try{
this.hideLangWindow();
}
catch(e){
}
this.gselm=elm;
this.createEditLayer(this.text_area.offsetWidth,this.text_area.offsetHeight);
this.createErrorWindow();
AJS.getBody().appendChild(this.error_window);
try{
netscape.security.PrivilegeManager.enablePrivilege("UniversalBrowserRead");
}
catch(e){
}
this.gselm.onclick=null;
this.orginal_text=this.text_area.value;
var me=this;
var d=AJS.getRequest(this.getGoogleUrl());
var _62=function(req){
var _64=req.responseText;
if(_64.match(/<c.*>/)!=null){
var _65=GoogieSpell.parseResult(_64);
me.results=_65;
me.showErrorsInIframe(_65);
me.resumeEditingState();
}else{
me.flashNoSpellingErrorState();
}
me.removeIndicator();
};
var _66=function(req){
alert("An error was encountered on the server. Please try again later.");
AJS.removeElement(me.gselm);
me.checkSpellingState();
me.removeIndicator();
};
d.addCallback(_62);
d.addErrback(_66);
var _68=GoogieSpell.escapeSepcial(this.orginal_text);
d.sendReq(GoogieSpell.createXMLReq(_68));
};
GoogieSpell.escapeSepcial=function(val){
return val.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;");
};
GoogieSpell.createXMLReq=function(_6a){
return "<?xml version=\"1.0\" encoding=\"utf-8\" ?><spellrequest textalreadyclipped=\"0\" ignoredups=\"0\" ignoredigits=\"1\" ignoreallcaps=\"1\"><text>"+_6a+"</text></spellrequest>";
};
GoogieSpell.parseResult=function(_6b){
var _6c=/\w="\d+"/g;
var _6d=/\t/g;
var _6e=_6b.match(/<c[^>]*>[^<]*<\/c>/g);
var _6f=new Array();
for(var i=0;i<_6e.length;i++){
var _71=new Array();
_71["attrs"]=new Array();
var _72=_6e[i].match(_6c);
for(var j=0;j<_72.length;j++){
var _74=_72[j].split(/=/);
_71["attrs"][_74[0]]=parseInt(_74[1].replace("\"",""));
}
_71["suggestions"]=new Array();
var _75=_6e[i].replace(/<[^>]*>/g,"");
var _76=_75.split(_6d);
for(var k=0;k<_76.length;k++){
if(_76[k]!=""){
_71["suggestions"].push(_76[k]);
}
}
_6f.push(_71);
}
return _6f;
};
GoogieSpell.prototype.createErrorWindow=function(){
this.error_window=AJS.DIV();
this.error_window.className="googie_window";
};
GoogieSpell.prototype.hideErrorWindow=function(){
this.error_window.style.visibility="hidden";
};
GoogieSpell.prototype.updateOrginalText=function(_78,_79,_7a,id){
var _7c=this.orginal_text.substring(0,_78);
var _7d=this.orginal_text.substring(_78+_79.length);
this.orginal_text=_7c+_7a+_7d;
var _7e=_7a.length-_79.length;
for(var j=0;j<this.results.length;j++){
if(j!=id&&j>id){
this.results[j]["attrs"]["o"]+=_7e;
}
}
};
GoogieSpell.prototype.saveOldValue=function(id,_81){
this.results[id]["is_changed"]=true;
this.results[id]["old_value"]=_81;
};
GoogieSpell.prototype.showErrorWindow=function(elm,id){
var me=this;
var _85=GoogieSpell.absolutePosition(elm);
_85.y-=this.edit_layer.scrollTop;
this.error_window.style.visibility="visible";
this.error_window.style.top=(_85.y+20)+"px";
this.error_window.style.left=(_85.x)+"px";
this.error_window.innerHTML="";
var _86=AJS.TABLE({"class":"googie_list"});
var _87=AJS.TBODY();
var _88=this.results[id]["suggestions"];
var _89=this.results[id]["attrs"]["o"];
var len=this.results[id]["attrs"]["l"];
if(_88.length==0){
var row=AJS.TR();
var _8c=AJS.TD();
var _8d=AJS.SPAN();
_8c.appendChild(AJS.TN("No suggestions :("));
row.appendChild(_8c);
_87.appendChild(row);
}
for(i=0;i<_88.length;i++){
var row=AJS.TR();
var _8c=AJS.TD();
var _8d=AJS.SPAN();
_8d.innerHTML=_88[i];
_8c.appendChild(AJS.TN(_8d.innerHTML));
_8c.onclick=function(e){
var _8f=GoogieSpell.getEventElm(e);
var _90=elm.innerHTML;
var _91=_8f.innerHTML;
elm.style.color="green";
elm.innerHTML=_8f.innerHTML;
me.hideErrorWindow();
me.updateOrginalText(_89,_90,_91,id);
me.results[id]["attrs"]["l"]=_91.length;
me.saveOldValue(id,_90);
};
_8c.onmouseover=GoogieSpell.item_onmouseover;
_8c.onmouseout=GoogieSpell.item_onmouseout;
row.appendChild(_8c);
_87.appendChild(row);
}
if(this.results[id]["is_changed"]){
var _92=this.results[id]["old_value"];
var _89=this.results[id]["attrs"]["o"];
var _93=AJS.TR();
var _94=AJS.TD();
_94.onmouseover=GoogieSpell.item_onmouseover;
_94.onmouseout=GoogieSpell.item_onmouseout;
var _95=AJS.SPAN({"class":"googie_list_revert"});
_95.innerHTML=this.lang_revert+" "+_92;
_94.appendChild(_95);
_94.onclick=function(e){
me.updateOrginalText(_89,elm.innerHTML,_92,id);
elm.style.color="#b91414";
elm.innerHTML=_92;
me.hideErrorWindow();
};
_93.appendChild(_94);
_87.appendChild(_93);
}
var _97=AJS.TR();
var _98=AJS.TD();
var _99=AJS.INPUT({"style":"width: 120px; margin:0; padding:0"});
var _9a=function(){
if(_99.value!=""){
me.saveOldValue(id,elm.innerHTML);
me.updateOrginalText(_89,elm.innerHTML,_99.value,id);
elm.style.color="green";
elm.innerHTML=_99.value;
me.hideErrorWindow();
return false;
}
};
var _9b=AJS.IMG({"src":this.img_dir+"ok.gif","style":"width: 32px; height: 16px; margin-left: 2px; margin-right: 2px;"});
var _9c=AJS.FORM({"style":"margin: 0; padding: 0"},_99,_9b);
_9b.onclick=_9a;
_9c.onsubmit=_9a;
_98.appendChild(_9c);
_97.appendChild(_98);
_87.appendChild(_97);
var _9d=AJS.TR();
var _9e=AJS.TD();
_9e.onmouseover=GoogieSpell.item_onmouseover;
_9e.onmouseout=GoogieSpell.item_onmouseout;
var _9f=AJS.SPAN({"class":"googie_list_close"});
_9f.innerHTML=this.lang_close;
_9e.appendChild(_9f);
_9e.onclick=function(){
me.hideErrorWindow();
};
_9d.appendChild(_9e);
_87.appendChild(_9d);
_86.appendChild(_87);
this.error_window.appendChild(_86);
};
GoogieSpell.prototype.createEditLayer=function(_a0,_a1){
this.edit_layer=AJS.DIV({"class":"googie_edit_layer"});
this.edit_layer.className=this.text_area.className;
this.edit_layer.style.border="1px solid #999";
this.edit_layer.style.overflow="auto";
this.edit_layer.style.backgroundColor="#F1EDFE";
this.edit_layer.style.padding="3px";
this.edit_layer.style.width=(_a0-8)+"px";
this.edit_layer.style.height=_a1+"px";
};
GoogieSpell.prototype.resumeEditing=function(e,me){
this.setStateChanged("check_spelling");
me.switch_lan_pic.style.display="inline";
this.el_scroll_top=me.edit_layer.scrollTop;
var elm=GoogieSpell.getEventElm(e);
AJS.replaceChildNodes(elm,this.createSpellDiv());
elm.onclick=function(e){
me.spellCheck(elm,me.text_area.id);
};
me.hideErrorWindow();
me.edit_layer.parentNode.removeChild(me.edit_layer);
me.text_area.value=me.orginal_text;
AJS.showElement(me.text_area);
me.gselm.className="googie_no_style";
me.text_area.scrollTop=this.el_scroll_top;
elm.onmouseout=null;
};
GoogieSpell.prototype.createErrorLink=function(_a6,id){
var elm=AJS.SPAN({"class":"googie_link"});
var me=this;
elm.onclick=function(){
me.showErrorWindow(elm,id);
};
elm.innerHTML=_a6;
return elm;
};
GoogieSpell.createPart=function(_aa){
if(_aa==" "){
return AJS.TN(" ");
}
var _ab=AJS.SPAN();
var _ac=true;
var _ad=(navigator.userAgent.toLowerCase().indexOf("safari")!=-1);
var _ae=AJS.SPAN();
_aa=GoogieSpell.escapeSepcial(_aa);
_aa=_aa.replace(/\n/g,"<br>");
_aa=_aa.replace(/  /g," &nbsp;");
_aa=_aa.replace(/^ /g,"&nbsp;");
_aa=_aa.replace(/ $/g,"&nbsp;");
_ae.innerHTML=_aa;
return _ae;
};
GoogieSpell.prototype.showErrorsInIframe=function(_af){
var _b0=AJS.DIV();
_b0.style.textAlign="left";
var _b1=0;
for(var i=0;i<_af.length;i++){
var _b3=_af[i]["attrs"]["o"];
var len=_af[i]["attrs"]["l"];
var _b5=this.orginal_text.substring(_b1,_b3);
var _b6=GoogieSpell.createPart(_b5);
_b0.appendChild(_b6);
_b1+=_b3-_b1;
_b0.appendChild(this.createErrorLink(this.orginal_text.substr(_b3,len),i));
_b1+=len;
}
var _b7=this.orginal_text.substr(_b1,this.orginal_text.length);
var _b8=GoogieSpell.createPart(_b7);
_b0.appendChild(_b8);
this.edit_layer.appendChild(_b0);
AJS.hideElement(this.text_area);
this.text_area.parentNode.insertBefore(this.edit_layer,this.text_area.nextSibling);
this.edit_layer.scrollTop=this.ta_scroll_top;
};
GoogieSpell.Position=function(x,y){
this.x=x;
this.y=y;
};
GoogieSpell.absolutePosition=function(_bb){
var _bc=new GoogieSpell.Position(_bb.offsetLeft,_bb.offsetTop);
if(_bb.offsetParent){
var _bd=GoogieSpell.absolutePosition(_bb.offsetParent);
_bc.x+=_bd.x;
_bc.y+=_bd.y;
}
return _bc;
};
GoogieSpell.getEventElm=function(e){
var _bf;
if(!e){
var e=window.event;
}
if(e.target){
_bf=e.target;
}else{
if(e.srcElement){
_bf=e.srcElement;
}
}
if(_bf.nodeType==3){
_bf=_bf.parentNode;
}
return _bf;
};
GoogieSpell.prototype.removeIndicator=function(elm){
if(window.rcube_webmail_client){
rcube_webmail_client.set_busy(false);
}
};
GoogieSpell.prototype.appendIndicator=function(elm){
if(window.rcube_webmail_client){
rcube_webmail_client.set_busy(true,"checking");
}
};
GoogieSpell.prototype.createLangWindow=function(){
this.language_window=AJS.DIV({"class":"googie_window"});
this.language_window.style.width="130px";
var _c2=AJS.TABLE({"class":"googie_list"});
var _c3=AJS.TBODY();
this.lang_elms=new Array();
for(i=0;i<this.langlist_codes.length;i++){
var row=AJS.TR();
var _c5=AJS.TD();
_c5.googieId=this.langlist_codes[i];
this.lang_elms.push(_c5);
var _c6=AJS.SPAN();
_c6.innerHTML=this.lang_to_word[this.langlist_codes[i]];
_c5.appendChild(AJS.TN(_c6.innerHTML));
var me=this;
_c5.onclick=function(e){
var elm=GoogieSpell.getEventElm(e);
me.deHighlightCurSel();
me.setCurrentLanguage(elm.googieId);
if(me.lang_state_observer!=null){
me.lang_state_observer();
}
me.highlightCurSel();
me.hideLangWindow();
};
_c5.onmouseover=function(e){
var _cb=GoogieSpell.getEventElm(e);
if(_cb.className!="googie_list_selected"){
_cb.className="googie_list_onhover";
}
};
_c5.onmouseout=function(e){
var _cd=GoogieSpell.getEventElm(e);
if(_cd.className!="googie_list_selected"){
_cd.className="googie_list_onout";
}
};
row.appendChild(_c5);
_c3.appendChild(row);
}
this.highlightCurSel();
var _ce=AJS.TR();
var _cf=AJS.TD();
_cf.onmouseover=GoogieSpell.item_onmouseover;
_cf.onmouseout=GoogieSpell.item_onmouseout;
var _d0=AJS.SPAN({"class":"googie_list_close"});
_d0.innerHTML=this.lang_close;
_cf.appendChild(_d0);
var me=this;
_cf.onclick=function(e){
me.hideLangWindow();
GoogieSpell.item_onmouseout(e);
};
_ce.appendChild(_cf);
_c3.appendChild(_ce);
_c2.appendChild(_c3);
this.language_window.appendChild(_c2);
};
GoogieSpell.prototype.setCurrentLanguage=function(_d2){
GOOGIE_CUR_LANG=_d2;
var now=new Date();
now.setTime(now.getTime()+365*24*60*60*1000);
setCookie("language",_d2,now);
};
GoogieSpell.prototype.hideLangWindow=function(){
this.language_window.style.visibility="hidden";
this.switch_lan_pic.className="googie_lang_3d_on";
};
GoogieSpell.prototype.deHighlightCurSel=function(){
this.lang_cur_elm.className="googie_list_onout";
};
GoogieSpell.prototype.highlightCurSel=function(){
for(var i=0;i<this.lang_elms.length;i++){
if(this.lang_elms[i].googieId==GOOGIE_CUR_LANG){
this.lang_elms[i].className="googie_list_selected";
this.lang_cur_elm=this.lang_elms[i];
}else{
this.lang_elms[i].className="googie_list_onout";
}
}
};
GoogieSpell.prototype.showLangWindow=function(elm,_d6,_d7){
if(!AJS.isDefined(_d6)){
_d6=20;
}
if(!AJS.isDefined(_d7)){
_d7=50;
}
this.createLangWindow();
AJS.getBody().appendChild(this.language_window);
var _d8=GoogieSpell.absolutePosition(elm);
AJS.showElement(this.language_window);
this.language_window.style.top=(_d8.y+_d6)+"px";
this.language_window.style.left=(_d8.x+_d7-this.language_window.offsetWidth)+"px";
this.highlightCurSel();
this.language_window.style.visibility="visible";
};
GoogieSpell.prototype.flashNoSpellingErrorState=function(){
this.setStateChanged("no_error_found");
var me=this;
AJS.hideElement(this.switch_lan_pic);
this.gselm.innerHTML=this.lang_no_error_found;
this.gselm.className="googie_check_spelling_ok";
this.gselm.style.textDecoration="none";
this.gselm.style.cursor="default";
var fu=function(){
AJS.removeElement(me.gselm);
me.checkSpellingState();
};
setTimeout(fu,1000);
};
GoogieSpell.prototype.resumeEditingState=function(){
this.setStateChanged("resume_editing");
var me=this;
AJS.hideElement(me.switch_lan_pic);
me.gselm.innerHTML=this.lang_rsm_edt;
me.gselm.onclick=function(e){
me.resumeEditing(e,me);
};
me.gselm.className="googie_check_spelling_ok";
me.edit_layer.scrollTop=me.ta_scroll_top;
};
GoogieSpell.prototype.createChangeLangPic=function(){
var _dd=AJS.A({"class":"googie_lang_3d_on","style":"padding-left: 6px;"},AJS.IMG({"src":this.img_dir+"change_lang.gif","alt":"Change language"}));
_dd.onmouseover=function(){
if(this.className!="googie_lang_3d_click"){
this.className="googie_lang_3d_on";
}
};
var me=this;
_dd.onclick=function(){
if(this.className=="googie_lang_3d_click"){
me.hideLangWindow();
}else{
me.showLangWindow(_dd);
this.className="googie_lang_3d_click";
}
};
return _dd;
};
GoogieSpell.prototype.createSpellDiv=function(){
var _df=AJS.SPAN({"class":"googie_check_spelling_link"});
_df.innerHTML=this.lang_chck_spell;
var _e0=null;
if(this.show_spell_img){
_e0=AJS.IMG({"src":this.img_dir+"spellc.gif"});
}
return AJS.SPAN(_e0," ",_df);
};
GoogieSpell.prototype.checkSpellingState=function(){
this.setStateChanged("check_spelling");
var me=this;
if(this.show_change_lang_pic){
this.switch_lan_pic=this.createChangeLangPic();
}else{
this.switch_lan_pic=AJS.SPAN();
}
var _e2=this.createSpellDiv();
_e2.onclick=function(){
me.spellCheck(_e2);
};
AJS.appendChildNodes(this.spell_container,_e2," ",this.switch_lan_pic);
this.check_link=_e2;
};
GoogieSpell.prototype.setLanguages=function(_e3){
this.lang_to_word=_e3;
this.langlist_codes=AJS.keys(_e3);
};
GoogieSpell.prototype.decorateTextarea=function(id,_e5,_e6){
var me=this;
if(typeof (id)=="string"){
this.text_area=AJS.getElement(id);
}else{
this.text_area=id;
}
var _e8;
if(this.text_area!=null){
if(AJS.isDefined(_e5)){
if(typeof (_e5)=="string"){
this.spell_container=AJS.getElement(_e5);
}else{
this.spell_container=_e5;
}
}else{
var _e9=AJS.TABLE();
var _ea=AJS.TBODY();
var tr=AJS.TR();
if(AJS.isDefined(_e6)){
_e8=_e6;
}else{
_e8=this.text_area.offsetWidth+"px";
}
var _ec=AJS.TD();
this.spell_container=_ec;
tr.appendChild(_ec);
_ea.appendChild(tr);
_e9.appendChild(_ea);
AJS.insertBefore(_e9,this.text_area);
_e9.style.width="100%";
_ec.style.width=_e8;
_ec.style.textAlign="right";
}
this.checkSpellingState();
}else{
alert("Text area not found");
}
};

