var CONTROL_KEY=1;
var SHIFT_KEY=2;
var CONTROL_SHIFT_KEY=3;
function roundcube_browser(){
this.ver=parseFloat(navigator.appVersion);
this.appver=navigator.appVersion;
this.agent=navigator.userAgent;
this.name=navigator.appName;
this.vendor=navigator.vendor?navigator.vendor:"";
this.vendver=navigator.vendorSub?parseFloat(navigator.vendorSub):0;
this.product=navigator.product?navigator.product:"";
this.platform=String(navigator.platform).toLowerCase();
this.lang=(navigator.language)?navigator.language.substring(0,2):(navigator.browserLanguage)?navigator.browserLanguage.substring(0,2):(navigator.systemLanguage)?navigator.systemLanguage.substring(0,2):"en";
this.win=(this.platform.indexOf("win")>=0)?true:false;
this.mac=(this.platform.indexOf("mac")>=0)?true:false;
this.linux=(this.platform.indexOf("linux")>=0)?true:false;
this.unix=(this.platform.indexOf("unix")>=0)?true:false;
this.dom=document.getElementById?true:false;
this.dom2=(document.addEventListener&&document.removeEventListener);
this.ie=(document.all)?true:false;
this.ie4=(this.ie&&!this.dom);
this.ie5=(this.dom&&this.appver.indexOf("MSIE 5")>0);
this.ie6=(this.dom&&this.appver.indexOf("MSIE 6")>0);
this.ie7=(this.dom&&this.appver.indexOf("MSIE 7")>0);
this.mz=(this.dom&&this.ver>=5);
this.ns=((this.ver<5&&this.name=="Netscape")||(this.ver>=5&&this.vendor.indexOf("Netscape")>=0));
this.ns6=(this.ns&&parseInt(this.vendver)==6);
this.ns7=(this.ns&&parseInt(this.vendver)==7);
this.safari=(this.agent.toLowerCase().indexOf("safari")>0||this.agent.toLowerCase().indexOf("applewebkit")>0);
this.konq=(this.agent.toLowerCase().indexOf("konqueror")>0);
this.opera=(window.opera)?true:false;
if(this.opera&&window.RegExp){
this.vendver=(/opera(\s|\/)([0-9\.]+)/i.test(navigator.userAgent))?parseFloat(RegExp.$2):-1;
}else{
if(!this.vendver&&this.safari){
this.vendver=(/(safari|applewebkit)\/([0-9]+)/i.test(this.agent))?parseInt(RegExp.$2):0;
}else{
if((!this.vendver&&this.mz)||this.agent.indexOf("Camino")>0){
this.vendver=(/rv:([0-9\.]+)/.test(this.agent))?parseFloat(RegExp.$1):0;
}else{
if(this.ie&&window.RegExp){
this.vendver=(/msie\s+([0-9\.]+)/i.test(this.agent))?parseFloat(RegExp.$1):0;
}else{
if(this.konq&&window.RegExp){
this.vendver=(/khtml\/([0-9\.]+)/i.test(this.agent))?parseFloat(RegExp.$1):0;
}
}
}
}
}
if(this.safari&&(/;\s+([a-z]{2})-[a-z]{2}\)/i.test(this.agent))){
this.lang=RegExp.$1;
}
this.dhtml=((this.ie4&&this.win)||this.ie5||this.ie6||this.ns4||this.mz);
this.vml=(this.win&&this.ie&&this.dom&&!this.opera);
this.pngalpha=(this.mz||(this.opera&&this.vendver>=6)||(this.ie&&this.mac&&this.vendver>=5)||(this.ie&&this.win&&this.vendver>=5.5)||this.safari);
this.opacity=(this.mz||(this.ie&&this.vendver>=5.5&&!this.opera)||(this.safari&&this.vendver>=100));
this.cookies=navigator.cookieEnabled;
this.xmlhttp_test=function(){
var _1=new Function("try{var o=new ActiveXObject('Microsoft.XMLHTTP');return true;}catch(err){return false;}");
this.xmlhttp=(window.XMLHttpRequest||(window.ActiveXObject&&_1()))?true:false;
return this.xmlhttp;
};
};
var rcube_event={get_target:function(e){
e=e||window.event;
return e&&e.target?e.target:e.srcElement;
},get_keycode:function(e){
e=e||window.event;
return e&&e.keyCode?e.keyCode:(e&&e.which?e.which:0);
},get_button:function(e){
e=e||window.event;
return e&&(typeof e.button!="undefined")?e.button:(e&&e.which?e.which:0);
},get_modifier:function(e){
var _6=0;
e=e||window.event;
if(bw.mac&&e){
_6+=(e.metaKey&&CONTROL_KEY)+(e.shiftKey&&SHIFT_KEY);
return _6;
}
if(e){
_6+=(e.ctrlKey&&CONTROL_KEY)+(e.shiftKey&&SHIFT_KEY);
return _6;
}
},get_mouse_pos:function(e){
if(!e){
e=window.event;
}
var mX=(e.pageX)?e.pageX:e.clientX;
var mY=(e.pageY)?e.pageY:e.clientY;
if(document.body&&document.all){
mX+=document.body.scrollLeft;
mY+=document.body.scrollTop;
}
if(e._offset){
mX+=e._offset.x;
mY+=e._offset.y;
}
return {x:mX,y:mY};
},add_listener:function(p){
if(!p.object||!p.method){
return;
}
if(!p.element){
p.element=document;
}
if(!p.object._rc_events){
p.object._rc_events=[];
}
var _b=p.event+"*"+p.method;
if(!p.object._rc_events[_b]){
p.object._rc_events[_b]=function(e){
return p.object[p.method](e);
};
}
if(p.element.addEventListener){
p.element.addEventListener(p.event,p.object._rc_events[_b],false);
}else{
if(p.element.attachEvent){
p.element.detachEvent("on"+p.event,p.object._rc_events[_b]);
p.element.attachEvent("on"+p.event,p.object._rc_events[_b]);
}else{
p.element["on"+p.event]=p.object._rc_events[_b];
}
}
},remove_listener:function(p){
if(!p.element){
p.element=document;
}
var _e=p.event+"*"+p.method;
if(p.object&&p.object._rc_events&&p.object._rc_events[_e]){
if(p.element.removeEventListener){
p.element.removeEventListener(p.event,p.object._rc_events[_e],false);
}else{
if(p.element.detachEvent){
p.element.detachEvent("on"+p.event,p.object._rc_events[_e]);
}else{
p.element["on"+p.event]=null;
}
}
}
},cancel:function(_f){
var e=_f?_f:window.event;
if(e.preventDefault){
e.preventDefault();
}
if(e.stopPropagation){
e.stopPropagation();
}
e.cancelBubble=true;
e.returnValue=false;
return false;
}};
var rcube_layer_objects=new Array();
function rcube_layer(id,_12){
this.name=id;
this.create=function(arg){
var l=(arg.x)?arg.x:0;
var t=(arg.y)?arg.y:0;
var w=arg.width;
var h=arg.height;
var z=arg.zindex;
var vis=arg.vis;
var _1a=arg.parent;
var obj;
obj=document.createElement("DIV");
with(obj){
id=this.name;
with(style){
position="absolute";
visibility=(vis)?(vis==2)?"inherit":"visible":"hidden";
left=l+"px";
top=t+"px";
if(w){
width=w.toString().match(/\%$/)?w:w+"px";
}
if(h){
height=h.toString().match(/\%$/)?h:h+"px";
}
if(z){
zIndex=z;
}
}
}
if(_1a){
_1a.appendChild(obj);
}else{
document.body.appendChild(obj);
}
this.elm=obj;
};
if(_12!=null){
this.create(_12);
this.name=this.elm.id;
}else{
this.elm=document.getElementById(id);
}
if(!this.elm){
return false;
}
this.css=this.elm.style;
this.event=this.elm;
this.width=this.elm.offsetWidth;
this.height=this.elm.offsetHeight;
this.x=parseInt(this.elm.offsetLeft);
this.y=parseInt(this.elm.offsetTop);
this.visible=(this.css.visibility=="visible"||this.css.visibility=="show"||this.css.visibility=="inherit")?true:false;
this.id=rcube_layer_objects.length;
this.obj="rcube_layer_objects["+this.id+"]";
rcube_layer_objects[this.id]=this;
this.move=function(x,y){
this.x=x;
this.y=y;
this.css.left=Math.round(this.x)+"px";
this.css.top=Math.round(this.y)+"px";
};
this.shift=function(x,y){
x=Math.round(x*100)/100;
y=Math.round(y*100)/100;
this.move(this.x+x,this.y+y);
};
this.resize=function(w,h){
this.css.width=w+"px";
this.css.height=h+"px";
this.width=w;
this.height=h;
};
this.clip=function(t,w,h,l){
this.css.clip="rect("+t+" "+w+" "+h+" "+l+")";
this.clip_height=h;
this.clip_width=w;
};
this.show=function(a){
if(a==1){
this.css.visibility="visible";
this.visible=true;
}else{
if(a==2){
this.css.visibility="inherit";
this.visible=true;
}else{
this.css.visibility="hidden";
this.visible=false;
}
}
};
this.write=function(_27){
this.elm.innerHTML=_27;
};
this.set_bgcolor=function(c){
if(!c||c=="#"){
c="transparent";
}
this.css.backgroundColor=c;
};
this.set_opacity=function(v){
if(!bw.opacity){
return;
}
var op=v<=1?Math.round(v*100):parseInt(v);
if(bw.ie){
this.css.filter="alpha(opacity:"+op+")";
}else{
if(bw.safari){
this.css.opacity=op/100;
this.css.KhtmlOpacity=op/100;
}else{
if(bw.mz){
this.css.MozOpacity=op/100;
}
}
}
};
};
function rcube_check_email(_2b,_2c){
if(_2b&&window.RegExp){
var _2d="[^\\x0d\\x22\\x5c\\x80-\\xff]";
var _2e="[^\\x0d\\x5b-\\x5d\\x80-\\xff]";
var _2f="[^\\x00-\\x20\\x22\\x28\\x29\\x2c\\x2e\\x3a-\\x3c\\x3e\\x40\\x5b-\\x5d\\x7f-\\xff]+";
var _30="\\x5c[\\x00-\\x7f]";
var _31="\\x5b("+_2e+"|"+_30+")*\\x5d";
var _32="\\x22("+_2d+"|"+_30+")*\\x22";
var _33="("+_2f+"|"+_31+")";
var _34="("+_2f+"|"+_32+")";
var _35=_33+"(\\x2e"+_33+")*";
var _36=_34+"(\\x2e"+_34+")*";
var _37=_36+"\\x40"+_35;
var _38="[,;s\n]";
var _39=_2c?new RegExp("(^|<|"+_38+")"+_37+"($|>|"+_38+")","i"):new RegExp("^"+_37+"$","i");
return _39.test(_2b)?true:false;
}
return false;
};
function find_in_array(){
var _3a=find_in_array.arguments;
if(!_3a.length){
return -1;
}
var _3b=typeof (_3a[0])=="object"?_3a[0]:_3a.length>1&&typeof (_3a[1])=="object"?_3a[1]:new Array();
var _3c=typeof (_3a[0])!="object"?_3a[0]:_3a.length>1&&typeof (_3a[1])!="object"?_3a[1]:"";
var _3d=_3a.length==3?_3a[2]:false;
if(!_3b.length){
return -1;
}
for(var i=0;i<_3b.length;i++){
if(_3d&&_3b[i].toLowerCase()==_3c.toLowerCase()){
return i;
}else{
if(_3b[i]==_3c){
return i;
}
}
}
return -1;
};
function urlencode(str){
return window.encodeURIComponent?encodeURIComponent(str):escape(str);
};
function rcube_find_object(id,d){
var n,f,obj,e;
if(!d){
d=document;
}
if(d.getElementsByName&&(e=d.getElementsByName(id))){
obj=e[0];
}
if(!obj&&d.getElementById){
obj=d.getElementById(id);
}
if(!obj&&d.all){
obj=d.all[id];
}
if(!obj&&d.images.length){
obj=d.images[id];
}
if(!obj&&d.forms.length){
for(f=0;f<d.forms.length;f++){
if(d.forms[f].name==id){
obj=d.forms[f];
}else{
if(d.forms[f].elements[id]){
obj=d.forms[f].elements[id];
}
}
}
}
if(!obj&&d.layers){
if(d.layers[id]){
obj=d.layers[id];
}
for(n=0;!obj&&n<d.layers.length;n++){
obj=rcube_find_object(id,d.layers[n].document);
}
}
return obj;
};
function rcube_get_object_pos(obj,_47){
if(typeof (obj)=="string"){
obj=rcube_find_object(obj);
}
if(!obj){
return {x:0,y:0};
}
var iX=(bw.layers)?obj.x:obj.offsetLeft;
var iY=(bw.layers)?obj.y:obj.offsetTop;
if(!_47&&(bw.ie||bw.mz)){
var elm=obj.offsetParent;
while(elm&&elm!=null){
iX+=elm.offsetLeft-(elm.parentNode&&elm.parentNode.scrollLeft?elm.parentNode.scrollLeft:0);
iY+=elm.offsetTop-(elm.parentNode&&elm.parentNode.scrollTop?elm.parentNode.scrollTop:0);
elm=elm.offsetParent;
}
}
return {x:iX,y:iY};
};
function rcube_mouse_is_over(ev,obj){
var _4d=rcube_event.get_mouse_pos(ev);
var pos=rcube_get_object_pos(obj);
return ((_4d.x>=pos.x)&&(_4d.x<(pos.x+obj.offsetWidth))&&(_4d.y>=pos.y)&&(_4d.y<(pos.y+obj.offsetHeight)));
};
function get_elements_computed_style(_4f,_50,_51){
if(arguments.length==2){
_51=_50;
}
var el=_4f;
if(typeof (_4f)=="string"){
el=rcube_find_object(_4f);
}
if(el&&el.currentStyle){
return el.currentStyle[_50];
}else{
if(el&&document.defaultView&&document.defaultView.getComputedStyle){
return document.defaultView.getComputedStyle(el,null).getPropertyValue(_51);
}else{
return false;
}
}
};
function setCookie(_53,_54,_55,_56,_57,_58){
var _59=_53+"="+escape(_54)+(_55?"; expires="+_55.toGMTString():"")+(_56?"; path="+_56:"")+(_57?"; domain="+_57:"")+(_58?"; secure":"");
document.cookie=_59;
};
roundcube_browser.prototype.set_cookie=setCookie;
function getCookie(_5a){
var dc=document.cookie;
var _5c=_5a+"=";
var _5d=dc.indexOf("; "+_5c);
if(_5d==-1){
_5d=dc.indexOf(_5c);
if(_5d!=0){
return null;
}
}else{
_5d+=2;
}
var end=document.cookie.indexOf(";",_5d);
if(end==-1){
end=dc.length;
}
return unescape(dc.substring(_5d+_5c.length,end));
};
roundcube_browser.prototype.get_cookie=getCookie;
function rcube_console(){
this.log=function(msg){
box=rcube_find_object("console");
if(box){
if(msg.charAt(msg.length-1)=="\n"){
box.value+=msg+"--------------------------------------\n";
}else{
box.value+=msg+"\n--------------------------------------\n";
}
}
};
this.reset=function(){
box=rcube_find_object("console");
if(box){
box.value="";
}
};
};
var bw=new roundcube_browser();
if(!window.console){
console=new rcube_console();
}
RegExp.escape=function(str){
return String(str).replace(/([.*+?^=!:${}()|[\]\/\\])/g,"\\$1");
};
if(bw.ie){
document._getElementById=document.getElementById;
document.getElementById=function(id){
var i=0;
var o=document._getElementById(id);
if(!o||o.id!=id){
while((o=document.all[i])&&o.id!=id){
i++;
}
}
return o;
};
}
function exec_event(_64,_65){
if(document.createEventObject){
var evt=document.createEventObject();
return _64.fireEvent("on"+_65,evt);
}else{
var evt=document.createEvent("HTMLEvents");
evt.initEvent(_65,true,true);
return !_64.dispatchEvent(evt);
}
};

