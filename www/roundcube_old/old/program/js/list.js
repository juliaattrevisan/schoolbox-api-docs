function rcube_list_widget(_1,p){
this.ENTER_KEY=13;
this.DELETE_KEY=46;
this.BACKSPACE_KEY=8;
this.list=_1?_1:null;
this.frame=null;
this.rows=[];
this.selection=[];
this.rowcount=0;
this.subject_col=-1;
this.shiftkey=false;
this.multiselect=false;
this.multi_selecting=false;
this.draggable=false;
this.keyboard=false;
this.toggleselect=false;
this.dont_select=false;
this.drag_active=false;
this.last_selected=0;
this.shift_start=0;
this.in_selection_before=false;
this.focused=false;
this.drag_mouse_start=null;
this.dblclick_time=600;
this.row_init=function(){
};
this.events={click:[],dblclick:[],select:[],keypress:[],dragstart:[],dragmove:[],dragend:[]};
if(p&&typeof (p)=="object"){
for(var n in p){
this[n]=p[n];
}
}
};
rcube_list_widget.prototype={init:function(){
if(this.list&&this.list.tBodies[0]){
this.rows=new Array();
this.rowcount=0;
var _4;
for(var r=0;r<this.list.tBodies[0].childNodes.length;r++){
_4=this.list.tBodies[0].childNodes[r];
while(_4&&(_4.nodeType!=1||_4.style.display=="none")){
_4=_4.nextSibling;
r++;
}
this.init_row(_4);
this.rowcount++;
}
this.frame=this.list.parentNode;
if(this.keyboard){
rcube_event.add_listener({element:document,event:"keyup",object:this,method:"key_press"});
rcube_event.add_listener({element:document,event:"keydown",object:this,method:"key_down"});
}
}
},init_row:function(_6){
if(_6&&String(_6.id).match(/rcmrow([a-z0-9\-_=]+)/i)){
var p=this;
var _8=RegExp.$1;
_6.uid=_8;
this.rows[_8]={uid:_8,id:_6.id,obj:_6,classname:_6.className};
_6.onmousedown=function(e){
return p.drag_row(e,this.uid);
};
_6.onmouseup=function(e){
return p.click_row(e,this.uid);
};
if(document.all){
_6.onselectstart=function(){
return false;
};
}
this.row_init(this.rows[_8]);
}
},clear:function(_b){
var _c=document.createElement("TBODY");
this.list.insertBefore(_c,this.list.tBodies[0]);
this.list.removeChild(this.list.tBodies[1]);
this.rows=new Array();
this.rowcount=0;
if(_b){
this.clear_selection();
}
},remove_row:function(_d,_e){
if(this.rows[_d].obj){
this.rows[_d].obj.style.display="none";
}
if(_e){
this.select_next();
}
this.rows[_d]=null;
this.rowcount--;
},insert_row:function(_f,_10){
var _11=this.list.tBodies[0];
if(_10&&_11.rows.length){
_11.insertBefore(_f,_11.firstChild);
}else{
_11.appendChild(_f);
}
this.init_row(_f);
this.rowcount++;
},focus:function(e){
this.focused=true;
for(var n=0;n<this.selection.length;n++){
id=this.selection[n];
if(this.rows[id]&&this.rows[id].obj){
this.set_classname(this.rows[id].obj,"selected",true);
this.set_classname(this.rows[id].obj,"unfocused",false);
}
}
if(e||(e=window.event)){
rcube_event.cancel(e);
}
},blur:function(){
var id;
this.focused=false;
for(var n=0;n<this.selection.length;n++){
id=this.selection[n];
if(this.rows[id]&&this.rows[id].obj){
this.set_classname(this.rows[id].obj,"selected",false);
this.set_classname(this.rows[id].obj,"unfocused",true);
}
}
},drag_row:function(e,id){
var _18=rcube_event.get_target(e);
if(this.dont_select||(_18&&(_18.tagName=="INPUT"||_18.tagName=="IMG"))){
return false;
}
if(rcube_event.get_button(e)==2){
return true;
}
this.in_selection_before=this.in_selection(id)?id:false;
if(!this.in_selection_before){
var _19=rcube_event.get_modifier(e);
this.select_row(id,_19,false);
}
if(this.draggable&&this.selection.length){
this.drag_start=true;
this.drag_mouse_start=rcube_event.get_mouse_pos(e);
rcube_event.add_listener({element:document,event:"mousemove",object:this,method:"drag_mouse_move"});
rcube_event.add_listener({element:document,event:"mouseup",object:this,method:"drag_mouse_up"});
var _1a=document.getElementsByTagName("IFRAME");
this.iframe_events=Object();
for(var n in _1a){
var _1c=null;
if(_1a[n].contentDocument){
_1c=_1a[n].contentDocument;
}else{
if(_1a[n].contentWindow){
_1c=_1a[n].contentWindow.document;
}else{
if(_1a[n].document){
_1c=_1a[n].document;
}
}
}
if(_1c){
var _1d=this;
var pos=rcube_get_object_pos(document.getElementById(_1a[n].id));
this.iframe_events[n]=function(e){
e._offset=pos;
return _1d.drag_mouse_move(e);
};
if(_1c.addEventListener){
_1c.addEventListener("mousemove",this.iframe_events[n],false);
}else{
if(_1a[n].attachEvent){
_1c.attachEvent("onmousemove",this.iframe_events[n]);
}else{
_1c["onmousemove"]=this.iframe_events[n];
}
}
rcube_event.add_listener({element:_1c,event:"mouseup",object:this,method:"drag_mouse_up"});
}
}
}
return false;
},click_row:function(e,id){
var now=new Date().getTime();
var _23=rcube_event.get_modifier(e);
var _24=rcube_event.get_target(e);
if((_24&&(_24.tagName=="INPUT"||_24.tagName=="IMG"))){
return false;
}
if(this.dont_select){
this.dont_select=false;
return false;
}
var _25=now-this.rows[id].clicked<this.dblclick_time;
if(!this.drag_active&&this.in_selection_before==id&&!_25){
this.select_row(id,_23,false);
}
this.drag_start=false;
this.in_selection_before=false;
if(this.rows&&_25&&this.in_selection(id)){
this.trigger_event("dblclick");
}else{
this.trigger_event("click");
}
if(!this.drag_active){
rcube_event.cancel(e);
}
this.rows[id].clicked=now;
return false;
},get_next_row:function(){
if(!this.rows){
return false;
}
var _26=this.rows[this.last_selected];
var _27=_26?_26.obj.nextSibling:null;
while(_27&&(_27.nodeType!=1||_27.style.display=="none")){
_27=_27.nextSibling;
}
return _27;
},get_prev_row:function(){
if(!this.rows){
return false;
}
var _28=this.rows[this.last_selected];
var _29=_28?_28.obj.previousSibling:null;
while(_29&&(_29.nodeType!=1||_29.style.display=="none")){
_29=_29.previousSibling;
}
return _29;
},get_last_row:function(){
if(this.rowcount){
var _2a=this.list.tBodies[0].rows;
for(var i=_2a.length-1;i>=0;i--){
if(_2a[i].id&&String(_2a[i].id).match(/rcmrow([a-z0-9\-_=]+)/i)&&this.rows[RegExp.$1]!=null){
return RegExp.$1;
}
}
}
return null;
},select_row:function(id,_2d,_2e){
var _2f=this.selection.join(",");
if(!this.multiselect){
_2d=0;
}
if(!this.shift_start){
this.shift_start=id;
}
if(!_2d){
this.shift_start=id;
this.highlight_row(id,false);
this.multi_selecting=false;
}else{
switch(_2d){
case SHIFT_KEY:
this.shift_select(id,false);
break;
case CONTROL_KEY:
if(!_2e){
this.highlight_row(id,true);
}
break;
case CONTROL_SHIFT_KEY:
this.shift_select(id,true);
break;
default:
this.highlight_row(id,false);
break;
}
this.multi_selecting=true;
}
if(this.selection.join(",")!=_2f){
this.trigger_event("select");
}
if(this.last_selected!=0&&this.rows[this.last_selected]){
this.set_classname(this.rows[this.last_selected].obj,"focused",false);
}
if(this.toggleselect&&this.last_selected==id){
this.clear_selection();
id=null;
}else{
this.set_classname(this.rows[id].obj,"focused",true);
}
if(!this.selection.length){
this.shift_start=null;
}
this.last_selected=id;
},select:function(id){
this.select_row(id,false);
this.scrollto(id);
},select_next:function(){
var _31=this.get_next_row();
var _32=this.get_prev_row();
var _33=(_31)?_31:_32;
if(_33){
this.select_row(_33.uid,false,false);
}
},shift_select:function(id,_35){
if(!this.rows[this.shift_start]||!this.selection.length){
this.shift_start=id;
}
var _36=this.rows[this.shift_start].obj.rowIndex;
var _37=this.rows[id].obj.rowIndex;
var i=((_36<_37)?_36:_37);
var j=((_36>_37)?_36:_37);
for(var n in this.rows){
if((this.rows[n].obj.rowIndex>=i)&&(this.rows[n].obj.rowIndex<=j)){
if(!this.in_selection(n)){
this.highlight_row(n,true);
}
}else{
if(this.in_selection(n)&&!_35){
this.highlight_row(n,true);
}
}
}
},in_selection:function(id){
for(var n in this.selection){
if(this.selection[n]==id){
return true;
}
}
return false;
},select_all:function(_3d){
if(!this.rows||!this.rows.length){
return false;
}
var _3e=this.selection.join(",");
this.clear_selection();
for(var n in this.rows){
if(!_3d||this.rows[n][_3d]==true){
this.last_selected=n;
this.highlight_row(n,true);
}
}
if(this.selection.join(",")!=_3e){
this.trigger_event("select");
}
this.focus();
return true;
},clear_selection:function(id){
var _41=this.selection.length;
if(id){
for(var n=0;n<this.selection.length;n++){
if(this.selection[n]==id){
this.selection.splice(n,1);
break;
}
}
}else{
for(var n=0;n<this.selection.length;n++){
if(this.rows[this.selection[n]]){
this.set_classname(this.rows[this.selection[n]].obj,"selected",false);
this.set_classname(this.rows[this.selection[n]].obj,"unfocused",false);
}
}
this.selection=new Array();
}
if(_41&&!this.selection.length){
this.trigger_event("select");
}
},get_selection:function(){
return this.selection;
},get_single_selection:function(){
if(this.selection.length==1){
return this.selection[0];
}else{
return null;
}
},highlight_row:function(id,_44){
if(this.rows[id]&&!_44){
if(this.selection.length>1||!this.in_selection(id)){
this.clear_selection();
this.selection[0]=id;
this.set_classname(this.rows[id].obj,"selected",true);
}
}else{
if(this.rows[id]){
if(!this.in_selection(id)){
this.selection[this.selection.length]=id;
this.set_classname(this.rows[id].obj,"selected",true);
}else{
var p=find_in_array(id,this.selection);
var _46=this.selection.slice(0,p);
var _47=this.selection.slice(p+1,this.selection.length);
this.selection=_46.concat(_47);
this.set_classname(this.rows[id].obj,"selected",false);
this.set_classname(this.rows[id].obj,"unfocused",false);
}
}
}
},key_press:function(e){
if(this.focused!=true){
return true;
}
var _49=rcube_event.get_keycode(e);
var _4a=rcube_event.get_modifier(e);
switch(_49){
case 40:
case 38:
case 63233:
case 63232:
rcube_event.cancel(e);
return this.use_arrow_key(_49,_4a);
default:
this.shiftkey=e.shiftKey;
this.key_pressed=_49;
this.trigger_event("keypress");
if(this.key_pressed==this.BACKSPACE_KEY){
return rcube_event.cancel(e);
}
}
return true;
},key_down:function(e){
switch(rcube_event.get_keycode(e)){
case 40:
case 38:
case 63233:
case 63232:
if(!rcube_event.get_modifier(e)&&this.focused){
return rcube_event.cancel(e);
}
default:
}
return true;
},use_arrow_key:function(_4c,_4d){
var _4e;
if(_4c==40||_4c==63233){
_4e=this.get_next_row();
}else{
if(_4c==38||_4c==63232){
_4e=this.get_prev_row();
}
}
if(_4e){
this.select_row(_4e.uid,_4d,true);
this.scrollto(_4e.uid);
}
return false;
},scrollto:function(id){
var row=this.rows[id].obj;
if(row&&this.frame){
var _51=Number(row.offsetTop);
if(_51<Number(this.frame.scrollTop)){
this.frame.scrollTop=_51;
}else{
if(_51+Number(row.offsetHeight)>Number(this.frame.scrollTop)+Number(this.frame.offsetHeight)){
this.frame.scrollTop=(_51+Number(row.offsetHeight))-Number(this.frame.offsetHeight);
}
}
}
},drag_mouse_move:function(e){
if(this.drag_start){
var m=rcube_event.get_mouse_pos(e);
if(!this.drag_mouse_start||(Math.abs(m.x-this.drag_mouse_start.x)<3&&Math.abs(m.y-this.drag_mouse_start.y)<3)){
return false;
}
if(!this.draglayer){
this.draglayer=new rcube_layer("rcmdraglayer",{x:0,y:0,vis:0,zindex:2000});
}
var _54="";
var c,i,_57,_58,obj;
for(var n=0;n<this.selection.length;n++){
if(n>12){
_54+="...";
break;
}
if(this.rows[this.selection[n]].obj){
obj=this.rows[this.selection[n]].obj;
_58="";
for(c=0,i=0;i<obj.childNodes.length;i++){
if(obj.childNodes[i].nodeName=="TD"){
if(((_57=obj.childNodes[i].firstChild)&&(_57.nodeType==3||_57.nodeName=="A"))&&(this.subject_col<0||(this.subject_col>=0&&this.subject_col==c))){
_58=_57.nodeType==3?_57.data:_57.innerHTML;
_54+=(_58.length>50?_58.substring(0,50)+"...":_58)+"<br />";
break;
}
c++;
}
}
}
}
this.draglayer.write(_54);
this.draglayer.show(1);
this.drag_active=true;
this.trigger_event("dragstart");
}
if(this.drag_active&&this.draglayer){
var pos=rcube_event.get_mouse_pos(e);
this.draglayer.move(pos.x+20,pos.y-5);
this.trigger_event("dragmove",e);
}
this.drag_start=false;
return false;
},drag_mouse_up:function(e){
document.onmousemove=null;
if(this.draglayer&&this.draglayer.visible){
this.draglayer.show(0);
}
this.drag_active=false;
this.trigger_event("dragend");
rcube_event.remove_listener({element:document,event:"mousemove",object:this,method:"drag_mouse_move"});
rcube_event.remove_listener({element:document,event:"mouseup",object:this,method:"drag_mouse_up"});
var _5d=document.getElementsByTagName("IFRAME");
for(var n in _5d){
var _5f;
if(_5d[n].contentDocument){
_5f=_5d[n].contentDocument;
}else{
if(_5d[n].contentWindow){
_5f=_5d[n].contentWindow.document;
}else{
if(_5d[n].document){
_5f=_5d[n].document;
}
}
}
if(_5f){
if(this.iframe_events[n]){
if(_5f.removeEventListener){
_5f.removeEventListener("mousemove",this.iframe_events[n],false);
}else{
if(_5f.detachEvent){
_5f.detachEvent("onmousemove",this.iframe_events[n]);
}else{
_5f["onmousemove"]=null;
}
}
}
rcube_event.remove_listener({element:_5f,event:"mouseup",object:this,method:"drag_mouse_up"});
}
}
this.focus();
return rcube_event.cancel(e);
},set_classname:function(obj,_61,set){
var reg=new RegExp("s*"+_61,"i");
if(!set&&obj.className.match(reg)){
obj.className=obj.className.replace(reg,"");
}else{
if(set&&!obj.className.match(reg)){
obj.className+=" "+_61;
}
}
},addEventListener:function(evt,_65){
if(this.events[evt]){
var _66=this.events[evt].length;
this.events[evt][_66]=_65;
return _66;
}else{
return false;
}
},removeEventListener:function(evt,_68){
if(this.events[evt]&&this.events[evt][_68]){
this.events[evt][_68]=null;
}
},trigger_event:function(evt,p){
if(this.events[evt]&&this.events[evt].length){
for(var i=0;i<this.events[evt].length;i++){
if(typeof (this.events[evt][i])=="function"){
this.events[evt][i](this,p);
}
}
}
}};

