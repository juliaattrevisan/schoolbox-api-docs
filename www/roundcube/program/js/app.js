var rcube_webmail_client;
function rcube_webmail(){
this.env=new Object();
this.labels=new Object();
this.buttons=new Object();
this.gui_objects=new Object();
this.commands=new Object();
this.onloads=new Array();
rcube_webmail_client=this;
this.ref="rcube_webmail_client";
var _1=this;
this.dblclick_time=500;
this.message_time=3000;
this.identifier_expr=new RegExp("[^0-9a-z-_]","gi");
this.mimetypes=new Array("text/plain","text/html","text/xml","image/jpeg","image/gif","image/png","application/x-javascript","application/pdf","application/x-shockwave-flash");
this.env.keep_alive=60;
this.env.request_timeout=180;
this.env.draft_autosave=0;
this.env.comm_path="./";
this.env.bin_path="./bin/";
this.env.blankpage="program/blank.gif";
this.set_env=function(p,_3){
if(p!=null&&typeof (p)=="object"&&!_3){
for(var n in p){
this.env[n]=p[n];
}
}else{
this.env[p]=_3;
}
};
this.add_label=function(_5,_6){
this.labels[_5]=_6;
};
this.register_button=function(_7,id,_9,_a,_b,_c){
if(!this.buttons[_7]){
this.buttons[_7]=new Array();
}
var _d={id:id,type:_9};
if(_a){
_d.act=_a;
}
if(_b){
_d.sel=_b;
}
if(_c){
_d.over=_c;
}
this.buttons[_7][this.buttons[_7].length]=_d;
};
this.gui_object=function(_e,id){
this.gui_objects[_e]=id;
};
this.add_onload=function(f){
this.onloads[this.onloads.length]=f;
};
this.init=function(){
var p=this;
this.task=this.env.task;
if(!bw.dom||!bw.xmlhttp_test()){
this.goto_url("error","_code=0x199");
return;
}
for(var n in this.gui_objects){
this.gui_objects[n]=rcube_find_object(this.gui_objects[n]);
}
if(this.env.framed&&parent.rcmail&&parent.rcmail.set_busy){
parent.rcmail.set_busy(false);
}
this.enable_command("logout","mail","addressbook","settings",true);
if(this.env.permaurl){
this.enable_command("permaurl",true);
}
switch(this.task){
case "mail":
if(this.gui_objects.messagelist){
this.message_list=new rcube_list_widget(this.gui_objects.messagelist,{multiselect:true,draggable:true,keyboard:true,dblclick_time:this.dblclick_time});
this.message_list.row_init=function(o){
p.init_message_row(o);
};
this.message_list.addEventListener("dblclick",function(o){
p.msglist_dbl_click(o);
});
this.message_list.addEventListener("keypress",function(o){
p.msglist_keypress(o);
});
this.message_list.addEventListener("select",function(o){
p.msglist_select(o);
});
this.message_list.addEventListener("dragstart",function(o){
p.drag_start(o);
});
this.message_list.addEventListener("dragmove",function(o,e){
p.drag_move(e);
});
this.message_list.addEventListener("dragend",function(o){
p.drag_active=false;
});
this.message_list.init();
this.enable_command("toggle_status","toggle_flag",true);
if(this.gui_objects.mailcontframe){
this.gui_objects.mailcontframe.onmousedown=function(e){
return p.click_on_list(e);
};
document.onmouseup=function(e){
return p.doc_mouse_up(e);
};
}else{
this.message_list.focus();
}
}
if(this.env.coltypes){
this.set_message_coltypes(this.env.coltypes);
}
this.enable_command("list","checkmail","compose","add-contact","search","reset-search","collapse-folder",true);
if(this.env.search_text!=null&&document.getElementById("quicksearchbox")!=null){
document.getElementById("quicksearchbox").value=this.env.search_text;
}
if(this.env.action=="show"||this.env.action=="preview"){
this.enable_command("show","reply","reply-all","forward","moveto","delete","mark","viewsource","print","load-attachment","load-headers",true);
if(this.env.next_uid){
this.enable_command("nextmessage",true);
this.enable_command("lastmessage",true);
}
if(this.env.prev_uid){
this.enable_command("previousmessage",true);
this.enable_command("firstmessage",true);
}
}
if(this.env.trash_mailbox&&this.env.mailbox!=this.env.trash_mailbox){
this.set_alttext("delete","movemessagetotrash");
}
if(this.env.action=="preview"&&this.env.framed&&parent.rcmail){
this.enable_command("compose","add-contact",false);
parent.rcmail.show_contentframe(true);
}
if((this.env.action=="show"||this.env.action=="preview")&&this.env.blockedobjects){
if(this.gui_objects.remoteobjectsmsg){
this.gui_objects.remoteobjectsmsg.style.display="block";
}
this.enable_command("load-images","always-load",true);
}
if(this.env.action=="compose"){
this.enable_command("add-attachment","send-attachment","remove-attachment","send",true);
if(this.env.spellcheck){
this.env.spellcheck.spelling_state_observer=function(s){
_1.set_spellcheck_state(s);
};
this.set_spellcheck_state("ready");
if(rcube_find_object("_is_html").value=="1"){
this.display_spellcheck_controls(false);
}
}
if(this.env.drafts_mailbox){
this.enable_command("savedraft",true);
}
document.onmouseup=function(e){
return p.doc_mouse_up(e);
};
}
if(this.env.messagecount){
this.enable_command("select-all","select-none","expunge",true);
}
if(this.purge_mailbox_test()){
this.enable_command("purge",true);
}
this.set_page_buttons();
if(this.env.action=="compose"){
this.init_messageform();
}
if(this.env.action=="print"){
window.print();
}
if(this.gui_objects.mailboxlist){
this.env.unread_counts={};
this.gui_objects.folderlist=this.gui_objects.mailboxlist;
this.http_request("getunread","");
}
if(this.env.mdn_request&&this.env.uid){
var _1f="_uid="+this.env.uid+"&_mbox="+urlencode(this.env.mailbox);
if(confirm(this.get_label("mdnrequest"))){
this.http_post("sendmdn",_1f);
}else{
this.http_post("mark",_1f+"&_flag=mdnsent");
}
}
break;
case "addressbook":
if(this.gui_objects.contactslist){
this.contact_list=new rcube_list_widget(this.gui_objects.contactslist,{multiselect:true,draggable:true,keyboard:true});
this.contact_list.addEventListener("keypress",function(o){
p.contactlist_keypress(o);
});
this.contact_list.addEventListener("select",function(o){
p.contactlist_select(o);
});
this.contact_list.addEventListener("dragstart",function(o){
p.drag_start(o);
});
this.contact_list.addEventListener("dragmove",function(o,e){
p.drag_move(e);
});
this.contact_list.addEventListener("dragend",function(o){
p.drag_active=false;
});
this.contact_list.init();
if(this.env.cid){
this.contact_list.highlight_row(this.env.cid);
}
if(this.gui_objects.contactslist.parentNode){
this.gui_objects.contactslist.parentNode.onmousedown=function(e){
return p.click_on_list(e);
};
document.onmouseup=function(e){
return p.doc_mouse_up(e);
};
}else{
this.contact_list.focus();
}
}
this.set_page_buttons();
if(this.env.address_sources&&this.env.address_sources[this.env.source]&&!this.env.address_sources[this.env.source].readonly){
this.enable_command("add",true);
}
if(this.env.cid){
this.enable_command("show","edit",true);
}
if((this.env.action=="add"||this.env.action=="edit")&&this.gui_objects.editform){
this.enable_command("save",true);
}else{
this.enable_command("search","reset-search","moveto","import",true);
}
if(this.contact_list&&this.contact_list.rowcount>0){
this.enable_command("export",true);
}
this.enable_command("list",true);
break;
case "settings":
this.enable_command("preferences","identities","save","folders",true);
if(this.env.action=="identities"||this.env.action=="edit-identity"||this.env.action=="add-identity"){
this.enable_command("add",this.env.identities_level<2);
this.enable_command("delete","edit",true);
}
if(this.env.action=="edit-identity"||this.env.action=="add-identity"){
this.enable_command("save",true);
}
if(this.env.action=="folders"){
this.enable_command("subscribe","unsubscribe","create-folder","rename-folder","delete-folder",true);
}
if(this.gui_objects.identitieslist){
this.identity_list=new rcube_list_widget(this.gui_objects.identitieslist,{multiselect:false,draggable:false,keyboard:false});
this.identity_list.addEventListener("select",function(o){
p.identity_select(o);
});
this.identity_list.init();
this.identity_list.focus();
if(this.env.iid){
this.identity_list.highlight_row(this.env.iid);
}
}
if(this.gui_objects.subscriptionlist){
this.init_subscription_list();
}
break;
case "login":
var _29=rcube_find_object("rcmloginuser");
var _2a=rcube_find_object("rcmloginpwd");
var _2b=rcube_find_object("rcmlogintz");
if(_29){
_29.onkeyup=function(e){
return rcmail.login_user_keyup(e);
};
}
if(_29&&_29.value==""){
_29.focus();
}else{
if(_2a){
_2a.focus();
}
}
if(_2b){
_2b.value=new Date().getTimezoneOffset()/-60;
}
this.enable_command("login",true);
break;
default:
break;
}
this.enable_command("logout",true);
this.loaded=true;
if(this.pending_message){
this.display_message(this.pending_message[0],this.pending_message[1]);
}
this.start_keepalive();
for(var i=0;i<this.onloads.length;i++){
if(typeof (this.onloads[i])=="string"){
eval(this.onloads[i]);
}else{
if(typeof (this.onloads[i])=="function"){
this.onloads[i]();
}
}
}
};
this.start_keepalive=function(){
if(this.env.keep_alive&&!this.env.framed&&this.task=="mail"&&this.gui_objects.mailboxlist){
this._int=setInterval(function(){
_1.check_for_recent(false);
},this.env.keep_alive*1000);
}else{
if(this.env.keep_alive&&!this.env.framed&&this.task!="login"){
this._int=setInterval(function(){
_1.send_keep_alive();
},this.env.keep_alive*1000);
}
}
};
this.init_message_row=function(row){
var uid=row.uid;
if(uid&&this.env.messages[uid]){
row.deleted=this.env.messages[uid].deleted?true:false;
row.unread=this.env.messages[uid].unread?true:false;
row.replied=this.env.messages[uid].replied?true:false;
row.flagged=this.env.messages[uid].flagged?true:false;
row.forwarded=this.env.messages[uid].forwarded?true:false;
}
if((row.icon=row.obj.cells[0].childNodes[0])&&row.icon.nodeName=="IMG"){
var p=this;
row.icon.id="msgicn_"+row.uid;
row.icon._row=row.obj;
row.icon.onmousedown=function(e){
p.command("toggle_status",this);
};
}
if(!this.env.flagged_col&&this.env.coltypes){
var _32;
if((_32=find_in_array("flag",this.env.coltypes))>=0){
this.set_env("flagged_col",_32+1);
}
}
if(this.env.flagged_col&&(row.flagged_icon=row.obj.cells[this.env.flagged_col].childNodes[0])&&row.flagged_icon.nodeName=="IMG"){
var p=this;
row.flagged_icon.id="flaggedicn_"+row.uid;
row.flagged_icon._row=row.obj;
row.flagged_icon.onmousedown=function(e){
p.command("toggle_flag",this);
};
}
};
this.init_messageform=function(){
if(!this.gui_objects.messageform){
return false;
}
var _34=rcube_find_object("_from");
var _35=rcube_find_object("_to");
var _36=rcube_find_object("_cc");
var _37=rcube_find_object("_bcc");
var _38=rcube_find_object("_replyto");
var _39=rcube_find_object("_subject");
var _3a=rcube_find_object("_message");
var _3b=rcube_find_object("_draft_saveid");
if(_35){
this.init_address_input_events(_35);
}
if(_36){
this.init_address_input_events(_36);
}
if(_37){
this.init_address_input_events(_37);
}
if(_34&&_34.type=="select-one"&&(!_3b||_3b.value=="")&&rcube_find_object("_is_html").value!="1"){
this.change_identity(_34);
}
if(_35&&_35.value==""){
_35.focus();
}else{
if(_39&&_39.value==""){
_39.focus();
}else{
if(_3a){
this.set_caret2start(_3a);
}
}
}
this.compose_field_hash(true);
this.auto_save_start();
};
this.init_address_input_events=function(obj){
var _3d=function(e){
return _1.ksearch_keypress(e,this);
};
if(obj.addEventListener){
obj.addEventListener(bw.safari?"keydown":"keypress",_3d,false);
}else{
obj.onkeydown=_3d;
}
obj.setAttribute("autocomplete","off");
};
this.command=function(_3f,_40,obj){
if(obj&&obj.blur){
obj.blur();
}
if(this.busy){
return false;
}
if(!this.commands[_3f]){
if(this.env.framed&&parent.rcmail&&parent.rcmail.command){
parent.rcmail.command(_3f,_40);
}
return false;
}
if(this.task=="mail"&&this.env.action=="compose"&&(_3f=="list"||_3f=="mail"||_3f=="addressbook"||_3f=="settings")){
if(this.cmp_hash!=this.compose_field_hash()&&!confirm(this.get_label("notsentwarning"))){
return false;
}
}
switch(_3f){
case "login":
if(this.gui_objects.loginform){
this.gui_objects.loginform.submit();
}
break;
case "logout":
this.goto_url("logout","",true);
break;
case "mail":
case "addressbook":
case "settings":
this.switch_task(_3f);
break;
case "permaurl":
if(obj&&obj.href&&obj.target){
return true;
}else{
if(this.env.permaurl){
parent.location.href=this.env.permaurl;
}
}
break;
case "list":
if(this.task=="mail"){
if(this.env.search_request<0||(_40!=""&&(this.env.search_request&&_40!=this.env.mailbox))){
this.reset_qsearch();
}
this.list_mailbox(_40);
if(this.env.trash_mailbox){
this.set_alttext("delete",this.env.mailbox!=this.env.trash_mailbox?"movemessagetotrash":"deletemessage");
}
}else{
if(this.task=="addressbook"){
if(this.env.search_request<0||(this.env.search_request&&_40!=this.env.source)){
this.reset_qsearch();
}
this.list_contacts(_40);
this.enable_command("add",(this.env.address_sources&&!this.env.address_sources[_40].readonly));
}
}
break;
case "load-headers":
this.load_headers(obj);
break;
case "sort":
var _42=_40.split("_");
var _43=_42[0];
var _44=_42[1]?_42[1].toUpperCase():null;
var _45;
if(_44==null){
if(this.env.sort_col==_43){
_44=this.env.sort_order=="ASC"?"DESC":"ASC";
}else{
_44=this.env.sort_order;
}
}
if(this.env.sort_col==_43&&this.env.sort_order==_44){
break;
}
if(_45=document.getElementById("rcm"+this.env.sort_col)){
this.set_classname(_45,"sorted"+(this.env.sort_order.toUpperCase()),false);
}
if(_45=document.getElementById("rcm"+_43)){
this.set_classname(_45,"sorted"+_44,true);
}
this.env.sort_col=_43;
this.env.sort_order=_44;
this.list_mailbox("","",_43+"_"+_44);
break;
case "nextpage":
this.list_page("next");
break;
case "lastpage":
this.list_page("last");
break;
case "previouspage":
this.list_page("prev");
break;
case "firstpage":
this.list_page("first");
break;
case "expunge":
if(this.env.messagecount){
this.expunge_mailbox(this.env.mailbox);
}
break;
case "purge":
case "empty-mailbox":
if(this.env.messagecount){
this.purge_mailbox(this.env.mailbox);
}
break;
case "show":
if(this.task=="mail"){
var uid=this.get_single_uid();
if(uid&&(!this.env.uid||uid!=this.env.uid)){
if(this.env.mailbox==this.env.drafts_mailbox){
this.goto_url("compose","_draft_uid="+uid+"&_mbox="+urlencode(this.env.mailbox),true);
}else{
this.show_message(uid);
}
}
}else{
if(this.task=="addressbook"){
var cid=_40?_40:this.get_single_cid();
if(cid&&!(this.env.action=="show"&&cid==this.env.cid)){
this.load_contact(cid,"show");
}
}
}
break;
case "add":
if(this.task=="addressbook"){
this.load_contact(0,"add");
}else{
if(this.task=="settings"){
this.identity_list.clear_selection();
this.load_identity(0,"add-identity");
}
}
break;
case "edit":
var cid;
if(this.task=="addressbook"&&(cid=this.get_single_cid())){
this.load_contact(cid,"edit");
}else{
if(this.task=="settings"&&_40){
this.load_identity(_40,"edit-identity");
}
}
break;
case "save-identity":
case "save":
if(this.gui_objects.editform){
var _48=rcube_find_object("_pagesize");
var _49=rcube_find_object("_name");
var _4a=rcube_find_object("_email");
if(_48&&isNaN(parseInt(_48.value))){
alert(this.get_label("nopagesizewarning"));
_48.focus();
break;
}else{
if(_49&&_49.value==""){
alert(this.get_label("nonamewarning"));
_49.focus();
break;
}else{
if(_4a&&!rcube_check_email(_4a.value)){
alert(this.get_label("noemailwarning"));
_4a.focus();
break;
}
}
}
this.gui_objects.editform.submit();
}
break;
case "delete":
if(this.task=="mail"){
this.delete_messages();
}else{
if(this.task=="addressbook"){
this.delete_contacts();
}else{
if(this.task=="settings"){
this.delete_identity();
}
}
}
break;
case "move":
case "moveto":
if(this.task=="mail"){
this.move_messages(_40);
}else{
if(this.task=="addressbook"&&this.drag_active){
this.copy_contact(null,_40);
}
}
break;
case "mark":
if(_40){
this.mark_message(_40);
}
break;
case "toggle_status":
if(_40&&!_40._row){
break;
}
var uid;
var _4b="read";
if(_40._row.uid){
uid=_40._row.uid;
if(this.message_list.rows[uid].deleted){
_4b="undelete";
}else{
if(!this.message_list.rows[uid].unread){
_4b="unread";
}
}
}
this.mark_message(_4b,uid);
break;
case "toggle_flag":
if(_40&&!_40._row){
break;
}
var uid;
var _4b="flagged";
if(_40._row.uid){
uid=_40._row.uid;
if(this.message_list.rows[uid].flagged){
_4b="unflagged";
}
}
this.mark_message(_4b,uid);
break;
case "always-load":
if(this.env.uid&&this.env.sender){
this.add_contact(urlencode(this.env.sender));
window.setTimeout(function(){
_1.command("load-images");
},300);
break;
}
case "load-images":
if(this.env.uid){
this.show_message(this.env.uid,true,this.env.action=="preview");
}
break;
case "load-attachment":
var _4c="_mbox="+urlencode(this.env.mailbox)+"&_uid="+this.env.uid+"&_part="+_40.part;
if(this.env.uid&&_40.mimetype&&find_in_array(_40.mimetype,this.mimetypes)>=0){
if(_40.mimetype=="text/html"){
_4c+="&_safe=1";
}
this.attachment_win=window.open(this.env.comm_path+"&_action=get&"+_4c+"&_frame=1","rcubemailattachment");
if(this.attachment_win){
window.setTimeout(function(){
_1.attachment_win.focus();
},10);
break;
}
}
this.goto_url("get",_4c+"&_download=1",false);
break;
case "select-all":
this.message_list.select_all(_40);
break;
case "select-none":
this.message_list.clear_selection();
break;
case "nextmessage":
if(this.env.next_uid){
this.show_message(this.env.next_uid,false,this.env.action=="preview");
}
break;
case "lastmessage":
if(this.env.last_uid){
this.show_message(this.env.last_uid);
}
break;
case "previousmessage":
if(this.env.prev_uid){
this.show_message(this.env.prev_uid,false,this.env.action=="preview");
}
break;
case "firstmessage":
if(this.env.first_uid){
this.show_message(this.env.first_uid);
}
break;
case "checkmail":
this.check_for_recent(true);
break;
case "compose":
var url=this.env.comm_path+"&_action=compose";
if(this.task=="mail"){
url+="&_mbox="+urlencode(this.env.mailbox);
if(this.env.mailbox==this.env.drafts_mailbox){
var uid;
if(uid=this.get_single_uid()){
url+="&_draft_uid="+uid;
}
}else{
if(_40){
url+="&_to="+urlencode(_40);
}
}
}else{
if(this.task=="addressbook"){
if(_40&&_40.indexOf("@")>0){
url=this.get_task_url("mail",url);
this.redirect(url+"&_to="+urlencode(_40));
break;
}
var _4e=new Array();
if(_40){
_4e[_4e.length]=_40;
}else{
if(this.contact_list){
var _4f=this.contact_list.get_selection();
for(var n=0;n<_4f.length;n++){
_4e[_4e.length]=_4f[n];
}
}
}
if(_4e.length){
this.http_request("mailto","_cid="+urlencode(_4e.join(","))+"&_source="+urlencode(this.env.source),true);
}
break;
}
}
url=url.replace(/&_framed=1/,"");
this.redirect(url);
break;
case "spellcheck":
if(window.tinyMCE&&tinyMCE.get("compose-body")){
tinyMCE.execCommand("mceSpellCheck",true);
}else{
if(this.env.spellcheck&&this.env.spellcheck.spellCheck&&this.spellcheck_ready){
this.env.spellcheck.spellCheck(this.env.spellcheck.check_link);
this.set_spellcheck_state("checking");
}
}
break;
case "savedraft":
self.clearTimeout(this.save_timer);
if(!this.gui_objects.messageform){
break;
}
if(!this.env.drafts_mailbox||this.cmp_hash==this.compose_field_hash()){
break;
}
this.set_busy(true,"savingmessage");
var _51=this.gui_objects.messageform;
_51.target="savetarget";
_51._draft.value="1";
_51.submit();
break;
case "send":
if(!this.gui_objects.messageform){
break;
}
if(!this.check_compose_input()){
break;
}
self.clearTimeout(this.save_timer);
this.set_busy(true,"sendingmessage");
var _51=this.gui_objects.messageform;
_51.target="savetarget";
_51._draft.value="";
_51.submit();
clearTimeout(this.request_timer);
break;
case "add-attachment":
this.show_attachment_form(true);
case "send-attachment":
self.clearTimeout(this.save_timer);
this.upload_file(_40);
break;
case "remove-attachment":
this.remove_attachment(_40);
break;
case "reply-all":
case "reply":
var uid;
if(uid=this.get_single_uid()){
this.goto_url("compose","_reply_uid="+uid+"&_mbox="+urlencode(this.env.mailbox)+(_3f=="reply-all"?"&_all=1":""),true);
}
break;
case "forward":
var uid;
if(uid=this.get_single_uid()){
this.goto_url("compose","_forward_uid="+uid+"&_mbox="+urlencode(this.env.mailbox),true);
}
break;
case "print":
var uid;
if(uid=this.get_single_uid()){
_1.printwin=window.open(this.env.comm_path+"&_action=print&_uid="+uid+"&_mbox="+urlencode(this.env.mailbox)+(this.env.safemode?"&_safe=1":""));
if(this.printwin){
window.setTimeout(function(){
_1.printwin.focus();
},20);
if(this.env.action!="show"){
this.mark_message("read",uid);
}
}
}
break;
case "viewsource":
var uid;
if(uid=this.get_single_uid()){
_1.sourcewin=window.open(this.env.comm_path+"&_action=viewsource&_uid="+this.env.uid+"&_mbox="+urlencode(this.env.mailbox));
if(this.sourcewin){
window.setTimeout(function(){
_1.sourcewin.focus();
},20);
}
}
break;
case "add-contact":
this.add_contact(_40);
break;
case "search":
if(!_40&&this.gui_objects.qsearchbox){
_40=this.gui_objects.qsearchbox.value;
}
if(_40){
this.qsearch(_40);
break;
}
case "reset-search":
var s=this.env.search_request;
this.reset_qsearch();
if(s&&this.env.mailbox){
this.list_mailbox(this.env.mailbox);
}else{
if(s&&this.task=="addressbook"){
this.list_contacts(this.env.source);
}
}
break;
case "import":
if(this.env.action=="import"&&this.gui_objects.importform){
var _53=document.getElementById("rcmimportfile");
if(_53&&!_53.value){
alert(this.get_label("selectimportfile"));
break;
}
this.gui_objects.importform.submit();
this.set_busy(true,"importwait");
this.lock_form(this.gui_objects.importform,true);
}else{
this.goto_url("import");
}
break;
case "export":
if(this.contact_list.rowcount>0){
var _54=(this.env.source?"_source="+urlencode(this.env.source)+"&":"");
if(this.env.search_request){
_54+="_search="+this.env.search_request;
}
this.goto_url("export",_54);
}
break;
case "collapse-folder":
if(_40){
this.collapse_folder(_40);
}
break;
case "preferences":
this.goto_url("");
break;
case "identities":
this.goto_url("identities");
break;
case "delete-identity":
this.delete_identity();
case "folders":
this.goto_url("folders");
break;
case "subscribe":
this.subscribe_folder(_40);
break;
case "unsubscribe":
this.unsubscribe_folder(_40);
break;
case "create-folder":
this.create_folder(_40);
break;
case "rename-folder":
this.rename_folder(_40);
break;
case "delete-folder":
this.delete_folder(_40);
break;
}
return obj?false:true;
};
this.enable_command=function(){
var _55=arguments;
if(!_55.length){
return -1;
}
var _56;
var _57=_55[_55.length-1];
for(var n=0;n<_55.length-1;n++){
_56=_55[n];
this.commands[_56]=_57;
this.set_button(_56,(_57?"act":"pas"));
}
return true;
};
this.set_busy=function(a,_5a){
if(a&&_5a){
var msg=this.get_label(_5a);
if(msg==_5a){
msg="Loading...";
}
this.display_message(msg,"loading",true);
}else{
if(!a){
this.hide_message();
}
}
this.busy=a;
if(this.gui_objects.editform){
this.lock_form(this.gui_objects.editform,a);
}
if(this.request_timer){
clearTimeout(this.request_timer);
}
if(a&&this.env.request_timeout){
this.request_timer=window.setTimeout(function(){
_1.request_timed_out();
},this.env.request_timeout*1000);
}
};
this.get_label=function(_5c){
if(this.labels[_5c]){
return this.labels[_5c];
}else{
return _5c;
}
};
this.switch_task=function(_5d){
if(this.task===_5d&&_5d!="mail"){
return;
}
var url=this.get_task_url(_5d);
if(_5d=="mail"){
url+="&_mbox=INBOX";
}
this.redirect(url);
};
this.get_task_url=function(_5f,url){
if(!url){
url=this.env.comm_path;
}
return url.replace(/_task=[a-z]+/,"_task="+_5f);
};
this.request_timed_out=function(){
this.set_busy(false);
this.display_message("Request timed out!","error");
};
this.doc_mouse_up=function(e){
var _62,li;
if(this.message_list){
this.message_list.blur();
_62=this.env.mailboxes;
}else{
if(this.contact_list){
this.contact_list.blur();
_62=this.env.address_sources;
}else{
if(this.ksearch_value){
this.ksearch_blur();
}
}
}
if(this.drag_active&&_62&&this.env.last_folder_target){
this.set_classname(this.get_folder_li(this.env.last_folder_target),"droptarget",false);
this.command("moveto",_62[this.env.last_folder_target].id);
this.env.last_folder_target=null;
}
};
this.drag_start=function(_64){
var _65=this.task=="mail"?this.env.mailboxes:this.env.address_sources;
this.drag_active=true;
if(this.preview_timer){
clearTimeout(this.preview_timer);
}
if(this.gui_objects.folderlist&&_65){
var li,pos,_64,_68;
_64=rcube_find_object(this.task=="mail"?"mailboxlist":"directorylist");
pos=rcube_get_object_pos(_64);
this.env.folderlist_coords={x1:pos.x,y1:pos.y,x2:pos.x+_64.offsetWidth,y2:pos.y+_64.offsetHeight};
this.env.folder_coords=new Array();
for(var k in _65){
if(li=this.get_folder_li(k)){
pos=rcube_get_object_pos(li.firstChild);
if(_68=li.firstChild.offsetHeight){
this.env.folder_coords[k]={x1:pos.x,y1:pos.y,x2:pos.x+li.firstChild.offsetWidth,y2:pos.y+_68};
}
}
}
}
};
this.drag_move=function(e){
if(this.gui_objects.folderlist&&this.env.folder_coords){
var li,pos,_6d;
_6d=rcube_event.get_mouse_pos(e);
pos=this.env.folderlist_coords;
if(_6d.x<pos.x1||_6d.x>=pos.x2||_6d.y<pos.y1||_6d.y>=pos.y2){
if(this.env.last_folder_target){
this.set_classname(this.get_folder_li(this.env.last_folder_target),"droptarget",false);
this.env.last_folder_target=null;
}
return;
}
for(var k in this.env.folder_coords){
pos=this.env.folder_coords[k];
if(this.check_droptarget(k)&&((_6d.x>=pos.x1)&&(_6d.x<pos.x2)&&(_6d.y>=pos.y1)&&(_6d.y<pos.y2))){
this.set_classname(this.get_folder_li(k),"droptarget",true);
this.env.last_folder_target=k;
}else{
this.set_classname(this.get_folder_li(k),"droptarget",false);
}
}
}
};
this.collapse_folder=function(id){
var div;
if((li=this.get_folder_li(id))&&(div=li.getElementsByTagName("div")[0])&&(div.className.match(/collapsed/)||div.className.match(/expanded/))){
var ul=li.getElementsByTagName("ul")[0];
if(div.className.match(/collapsed/)){
ul.style.display="";
this.set_classname(div,"collapsed",false);
this.set_classname(div,"expanded",true);
var reg=new RegExp("&"+urlencode(id)+"&");
this.set_env("collapsed_folders",this.env.collapsed_folders.replace(reg,""));
}else{
ul.style.display="none";
this.set_classname(div,"expanded",false);
this.set_classname(div,"collapsed",true);
this.set_env("collapsed_folders",this.env.collapsed_folders+"&"+urlencode(id)+"&");
if(this.env.mailbox.indexOf(id+this.env.delimiter)==0){
this.command("list",id);
}
}
if((bw.ie6||bw.ie7)&&li.nextSibling&&(li.nextSibling.getElementsByTagName("ul").length>0)&&li.nextSibling.getElementsByTagName("ul")[0].style&&(li.nextSibling.getElementsByTagName("ul")[0].style.display!="none")){
li.nextSibling.getElementsByTagName("ul")[0].style.display="none";
li.nextSibling.getElementsByTagName("ul")[0].style.display="";
}
this.http_post("save-pref","_name=collapsed_folders&_value="+urlencode(this.env.collapsed_folders));
this.set_unread_count_display(id,false);
}
};
this.click_on_list=function(e){
if(this.message_list){
this.message_list.focus();
}else{
if(this.contact_list){
this.contact_list.focus();
}
}
var _74;
if(_74=this.get_folder_li()){
this.set_classname(_74,"unfocused",true);
}
return rcube_event.get_button(e)==2?true:rcube_event.cancel(e);
};
this.msglist_select=function(_75){
if(this.preview_timer){
clearTimeout(this.preview_timer);
}
var _76=_75.selection.length==1;
if(this.env.mailbox==this.env.drafts_mailbox){
this.enable_command("reply","reply-all","forward",false);
this.enable_command("show",_76);
this.enable_command("delete","moveto","mark",(_75.selection.length>0?true:false));
}else{
this.enable_command("show","reply","reply-all","forward","print",_76);
this.enable_command("delete","moveto","mark",(_75.selection.length>0?true:false));
}
if(_76&&this.env.contentframe&&!_75.multi_selecting){
this.preview_timer=window.setTimeout(function(){
_1.msglist_get_preview();
},200);
}else{
if(this.env.contentframe){
this.show_contentframe(false);
}
}
};
this.msglist_dbl_click=function(_77){
if(this.preview_timer){
clearTimeout(this.preview_timer);
}
var uid=_77.get_single_selection();
if(uid&&this.env.mailbox==this.env.drafts_mailbox){
this.goto_url("compose","_draft_uid="+uid+"&_mbox="+urlencode(this.env.mailbox),true);
}else{
if(uid){
this.show_message(uid,false,false);
}
}
};
this.msglist_keypress=function(_79){
if(_79.key_pressed==_79.ENTER_KEY){
this.command("show");
}else{
if(_79.key_pressed==_79.DELETE_KEY){
this.command("delete");
}else{
if(_79.key_pressed==_79.BACKSPACE_KEY){
this.command("delete");
}else{
_79.shiftkey=false;
}
}
}
};
this.msglist_get_preview=function(){
var uid=this.get_single_uid();
if(uid&&this.env.contentframe&&!this.drag_active){
this.show_message(uid,false,true);
}else{
if(this.env.contentframe){
this.show_contentframe(false);
}
}
};
this.check_droptarget=function(id){
if(this.task=="mail"){
return (this.env.mailboxes[id]&&this.env.mailboxes[id].id!=this.env.mailbox&&!this.env.mailboxes[id].virtual);
}else{
if(this.task=="addressbook"){
return (id!=this.env.source&&this.env.address_sources[id]&&!this.env.address_sources[id].readonly);
}else{
if(this.task=="settings"){
return (id!=this.env.folder);
}
}
}
};
this.show_message=function(id,_7d,_7e){
if(!id){
return;
}
var _7f="";
var _80=_7e?"preview":"show";
var _81=window;
if(_7e&&this.env.contentframe&&window.frames&&window.frames[this.env.contentframe]){
_81=window.frames[this.env.contentframe];
_7f="&_framed=1";
}
if(_7d){
_7f="&_safe=1";
}
if(this.env.search_request){
_7f+="&_search="+this.env.search_request;
}
var url="&_action="+_80+"&_uid="+id+"&_mbox="+urlencode(this.env.mailbox)+_7f;
if(_80=="preview"&&String(_81.location.href).indexOf(url)>=0){
this.show_contentframe(true);
}else{
this.set_busy(true,"loading");
_81.location.href=this.env.comm_path+url;
if(_80=="preview"&&this.message_list&&this.message_list.rows[id]&&this.message_list.rows[id].unread){
this.set_message(id,"unread",false);
if(this.env.unread_counts[this.env.mailbox]){
this.env.unread_counts[this.env.mailbox]-=1;
this.set_unread_count(this.env.mailbox,this.env.unread_counts[this.env.mailbox],this.env.mailbox=="INBOX");
}
}
}
};
this.show_contentframe=function(_83){
var frm;
if(this.env.contentframe&&(frm=rcube_find_object(this.env.contentframe))){
if(!_83&&window.frames[this.env.contentframe]){
if(window.frames[this.env.contentframe].location.href.indexOf(this.env.blankpage)<0){
window.frames[this.env.contentframe].location.href=this.env.blankpage;
}
}else{
if(!bw.safari){
frm.style.display=_83?"block":"none";
}
}
}
if(!_83&&this.busy){
this.set_busy(false);
}
};
this.list_page=function(_85){
if(_85=="next"){
_85=this.env.current_page+1;
}
if(_85=="last"){
_85=this.env.pagecount;
}
if(_85=="prev"&&this.env.current_page>1){
_85=this.env.current_page-1;
}
if(_85=="first"&&this.env.current_page>1){
_85=1;
}
if(_85>0&&_85<=this.env.pagecount){
this.env.current_page=_85;
if(this.task=="mail"){
this.list_mailbox(this.env.mailbox,_85);
}else{
if(this.task=="addressbook"){
this.list_contacts(this.env.source,_85);
}
}
}
};
this.filter_mailbox=function(_86){
var _87;
if(this.gui_objects.qsearchbox){
_87=this.gui_objects.qsearchbox.value;
}
this.message_list.clear();
this.env.current_page=1;
this.set_busy(true,"searching");
this.http_request("search","_filter="+_86+(_87?"&_q="+urlencode(_87):"")+(this.env.mailbox?"&_mbox="+urlencode(this.env.mailbox):""),true);
};
this.list_mailbox=function(_88,_89,_8a){
this.last_selected=0;
var _8b="";
var _8c=window;
if(!_88){
_88=this.env.mailbox;
}
if(_8a){
_8b+="&_sort="+_8a;
}
if(this.env.search_request){
_8b+="&_search="+this.env.search_request;
}
if(!_89&&_88!=this.env.mailbox){
_89=1;
this.env.current_page=_89;
if(this.message_list){
this.message_list.clear_selection();
}
this.show_contentframe(false);
}
if(_88!=this.env.mailbox||(_88==this.env.mailbox&&!_89&&!_8a)){
_8b+="&_refresh=1";
}
this.select_folder(_88,this.env.mailbox);
this.env.mailbox=_88;
if(this.gui_objects.messagelist){
this.list_mailbox_remote(_88,_89,_8b);
return;
}
if(this.env.contentframe&&window.frames&&window.frames[this.env.contentframe]){
_8c=window.frames[this.env.contentframe];
_8b+="&_framed=1";
}
if(_88){
this.set_busy(true,"loading");
_8c.location.href=this.env.comm_path+"&_mbox="+urlencode(_88)+(_89?"&_page="+_89:"")+_8b;
}
};
this.list_mailbox_remote=function(_8d,_8e,_8f){
this.message_list.clear();
var url="_mbox="+urlencode(_8d)+(_8e?"&_page="+_8e:"");
this.set_busy(true,"loading");
this.http_request("list",url+_8f,true);
};
this.expunge_mailbox=function(_91){
var _92=false;
var _93="";
if(_91==this.env.mailbox){
_92=true;
this.set_busy(true,"loading");
_93="&_reload=1";
}
var url="_mbox="+urlencode(_91);
this.http_post("expunge",url+_93,_92);
};
this.purge_mailbox=function(_95){
var _96=false;
var _97="";
if(!confirm(this.get_label("purgefolderconfirm"))){
return false;
}
if(_95==this.env.mailbox){
_96=true;
this.set_busy(true,"loading");
_97="&_reload=1";
}
var url="_mbox="+urlencode(_95);
this.http_post("purge",url+_97,_96);
return true;
};
this.purge_mailbox_test=function(){
return (this.env.messagecount&&(this.env.mailbox==this.env.trash_mailbox||this.env.mailbox==this.env.junk_mailbox||this.env.mailbox.match("^"+RegExp.escape(this.env.trash_mailbox)+RegExp.escape(this.env.delimiter))||this.env.mailbox.match("^"+RegExp.escape(this.env.junk_mailbox)+RegExp.escape(this.env.delimiter))));
};
this.set_message_icon=function(uid){
var _9a;
var _9b=this.message_list.rows;
if(!_9b[uid]){
return false;
}
if(_9b[uid].deleted&&this.env.deletedicon){
_9a=this.env.deletedicon;
}else{
if(_9b[uid].replied&&this.env.repliedicon){
if(_9b[uid].forwarded&&this.env.forwardedrepliedicon){
_9a=this.env.forwardedrepliedicon;
}else{
_9a=this.env.repliedicon;
}
}else{
if(_9b[uid].forwarded&&this.env.forwardedicon){
_9a=this.env.forwardedicon;
}else{
if(_9b[uid].unread&&this.env.unreadicon){
_9a=this.env.unreadicon;
}else{
if(this.env.messageicon){
_9a=this.env.messageicon;
}
}
}
}
}
if(_9a&&_9b[uid].icon){
_9b[uid].icon.src=_9a;
}
_9a="";
if(_9b[uid].flagged&&this.env.flaggedicon){
_9a=this.env.flaggedicon;
}else{
if(!_9b[uid].flagged&&this.env.unflaggedicon){
_9a=this.env.unflaggedicon;
}
}
if(_9b[uid].flagged_icon&&_9a){
_9b[uid].flagged_icon.src=_9a;
}
};
this.set_message_status=function(uid,_9d,_9e){
var _9f=this.message_list.rows;
if(!_9f[uid]){
return false;
}
if(_9d=="unread"){
_9f[uid].unread=_9e;
}else{
if(_9d=="deleted"){
_9f[uid].deleted=_9e;
}else{
if(_9d=="replied"){
_9f[uid].replied=_9e;
}else{
if(_9d=="forwarded"){
_9f[uid].forwarded=_9e;
}else{
if(_9d=="flagged"){
_9f[uid].flagged=_9e;
}
}
}
}
}
this.env.messages[uid]=_9f[uid];
};
this.set_message=function(uid,_a1,_a2){
var _a3=this.message_list.rows;
if(!_a3[uid]){
return false;
}
if(_a1){
this.set_message_status(uid,_a1,_a2);
}
if(_a3[uid].unread&&_a3[uid].classname.indexOf("unread")<0){
_a3[uid].classname+=" unread";
this.set_classname(_a3[uid].obj,"unread",true);
}else{
if(!_a3[uid].unread&&_a3[uid].classname.indexOf("unread")>=0){
_a3[uid].classname=_a3[uid].classname.replace(/\s*unread/,"");
this.set_classname(_a3[uid].obj,"unread",false);
}
}
if(_a3[uid].deleted&&_a3[uid].classname.indexOf("deleted")<0){
_a3[uid].classname+=" deleted";
this.set_classname(_a3[uid].obj,"deleted",true);
}else{
if(!_a3[uid].deleted&&_a3[uid].classname.indexOf("deleted")>=0){
_a3[uid].classname=_a3[uid].classname.replace(/\s*deleted/,"");
this.set_classname(_a3[uid].obj,"deleted",false);
}
}
if(_a3[uid].flagged&&_a3[uid].classname.indexOf("flagged")<0){
_a3[uid].classname+=" flagged";
this.set_classname(_a3[uid].obj,"flagged",true);
}else{
if(!_a3[uid].flagged&&_a3[uid].classname.indexOf("flagged")>=0){
_a3[uid].classname=_a3[uid].classname.replace(/\s*flagged/,"");
this.set_classname(_a3[uid].obj,"flagged",false);
}
}
this.set_message_icon(uid);
};
this.move_messages=function(_a4){
if(!_a4||_a4==this.env.mailbox||(!this.env.uid&&(!this.message_list||!this.message_list.get_selection().length))){
return;
}
var _a5=false;
var _a6="&_target_mbox="+urlencode(_a4)+"&_from="+(this.env.action?this.env.action:"");
if(this.env.action=="show"){
_a5=true;
this.set_busy(true,"movingmessage");
}else{
if(!this.env.flag_for_deletion){
this.show_contentframe(false);
}
}
this.enable_command("reply","reply-all","forward","delete","mark","print",false);
this._with_selected_messages("moveto",_a5,_a6,(this.env.flag_for_deletion?false:true));
};
this.delete_messages=function(){
var _a7=this.message_list?this.message_list.get_selection():new Array();
if(!this.env.uid&&!_a7.length){
return;
}
if(this.env.trash_mailbox&&String(this.env.mailbox).toLowerCase()!=String(this.env.trash_mailbox).toLowerCase()){
if(this.message_list&&this.message_list.shiftkey){
if(confirm(this.get_label("deletemessagesconfirm"))){
this.permanently_remove_messages();
}
}else{
this.move_messages(this.env.trash_mailbox);
}
}else{
if(this.env.trash_mailbox&&String(this.env.mailbox).toLowerCase()==String(this.env.trash_mailbox).toLowerCase()){
this.permanently_remove_messages();
}else{
if(!this.env.trash_mailbox&&this.env.flag_for_deletion){
this.mark_message("delete");
if(this.env.action=="show"){
this.command("nextmessage","",this);
}else{
if(_a7.length==1){
this.message_list.select_next();
}
}
}else{
if(!this.env.trash_mailbox){
this.permanently_remove_messages();
}
}
}
}
};
this.permanently_remove_messages=function(){
if(!this.env.uid&&(!this.message_list||!this.message_list.get_selection().length)){
return;
}
this.show_contentframe(false);
this._with_selected_messages("delete",false,"&_from="+(this.env.action?this.env.action:""),true);
};
this._with_selected_messages=function(_a8,_a9,_aa,_ab){
var _ac=new Array();
if(this.env.uid){
_ac[0]=this.env.uid;
}else{
var _ad=this.message_list.get_selection();
var _ae=this.message_list.rows;
var id;
for(var n=0;n<_ad.length;n++){
id=_ad[n];
_ac[_ac.length]=id;
if(_ab){
this.message_list.remove_row(id,(n==_ad.length-1));
}else{
this.set_message_status(id,"deleted",true);
if(this.env.read_when_deleted){
this.set_message_status(id,"unread",false);
}
this.set_message(id);
}
}
}
if(this.env.search_request){
_aa+="&_search="+this.env.search_request;
}
this.http_post(_a8,"_uid="+_ac.join(",")+"&_mbox="+urlencode(this.env.mailbox)+_aa,_a9);
};
this.mark_message=function(_b1,uid){
var _b3=new Array();
var _b4=new Array();
var _b5=this.message_list?this.message_list.get_selection():new Array();
if(uid){
_b3[0]=uid;
}else{
if(this.env.uid){
_b3[0]=this.env.uid;
}else{
if(this.message_list){
for(var n=0;n<_b5.length;n++){
_b3[_b3.length]=_b5[n];
}
}
}
}
if(!this.message_list){
_b4=_b3;
}else{
for(var id,n=0;n<_b3.length;n++){
id=_b3[n];
if((_b1=="read"&&this.message_list.rows[id].unread)||(_b1=="unread"&&!this.message_list.rows[id].unread)||(_b1=="delete"&&!this.message_list.rows[id].deleted)||(_b1=="undelete"&&this.message_list.rows[id].deleted)||(_b1=="flagged"&&!this.message_list.rows[id].flagged)||(_b1=="unflagged"&&this.message_list.rows[id].flagged)){
_b4[_b4.length]=id;
}
}
}
if(!_b4.length){
return;
}
switch(_b1){
case "read":
case "unread":
this.toggle_read_status(_b1,_b4);
break;
case "delete":
case "undelete":
this.toggle_delete_status(_b4);
break;
case "flagged":
case "unflagged":
this.toggle_flagged_status(_b1,_b3);
break;
}
};
this.toggle_read_status=function(_b8,_b9){
for(var i=0;i<_b9.length;i++){
this.set_message(_b9[i],"unread",(_b8=="unread"?true:false));
}
this.http_post("mark","_uid="+_b9.join(",")+"&_flag="+_b8);
};
this.toggle_flagged_status=function(_bb,_bc){
for(var i=0;i<_bc.length;i++){
this.set_message(_bc[i],"flagged",(_bb=="flagged"?true:false));
}
this.http_post("mark","_uid="+_bc.join(",")+"&_flag="+_bb);
};
this.toggle_delete_status=function(_be){
var _bf=this.message_list?this.message_list.rows:new Array();
if(_be.length==1){
if(!_bf.length||(_bf[_be[0]]&&!_bf[_be[0]].deleted)){
this.flag_as_deleted(_be);
}else{
this.flag_as_undeleted(_be);
}
return true;
}
var _c0=true;
for(var i=0;i<_be.length;i++){
uid=_be[i];
if(_bf[uid]){
if(!_bf[uid].deleted){
_c0=false;
break;
}
}
}
if(_c0){
this.flag_as_undeleted(_be);
}else{
this.flag_as_deleted(_be);
}
return true;
};
this.flag_as_undeleted=function(_c2){
for(var i=0;i<_c2.length;i++){
this.set_message(_c2[i],"deleted",false);
}
this.http_post("mark","_uid="+_c2.join(",")+"&_flag=undelete");
return true;
};
this.flag_as_deleted=function(_c4){
var _c5="";
var _c6=new Array();
var _c7=this.message_list?this.message_list.rows:new Array();
for(var i=0;i<_c4.length;i++){
uid=_c4[i];
if(_c7[uid]){
this.set_message(uid,"deleted",true);
if(_c7[uid].unread){
_c6[_c6.length]=uid;
}
}
}
if(_c6.length){
_c5="&_ruid="+_c6.join(",");
}
this.http_post("mark","_uid="+_c4.join(",")+"&_flag=delete"+_c5);
return true;
};
this.flag_deleted_as_read=function(_c9){
var _ca;
var _cb=this.message_list?this.message_list.rows:new Array();
var str=String(_c9);
var _cd=new Array();
_cd=str.split(",");
for(var uid,i=0;i<_cd.length;i++){
uid=_cd[i];
if(_cb[uid]){
this.set_message(uid,"unread",false);
}
}
};
this.login_user_keyup=function(e){
var key=rcube_event.get_keycode(e);
var elm;
if((key==13)&&(elm=rcube_find_object("_pass"))){
elm.focus();
return false;
}
};
this.check_compose_input=function(){
var _d3=rcube_find_object("_to");
var _d4=rcube_find_object("_cc");
var _d5=rcube_find_object("_bcc");
var _d6=rcube_find_object("_from");
var _d7=rcube_find_object("_subject");
var _d8=rcube_find_object("_message");
if(_d6.type=="text"&&!rcube_check_email(_d6.value,true)){
alert(this.get_label("nosenderwarning"));
_d6.focus();
return false;
}
var _d9=_d3.value?_d3.value:(_d4.value?_d4.value:_d5.value);
if(!rcube_check_email(_d9.replace(/^\s+/,"").replace(/[\s,;]+$/,""),true)){
alert(this.get_label("norecipientwarning"));
_d3.focus();
return false;
}
if(_d7&&_d7.value==""){
var _da=prompt(this.get_label("nosubjectwarning"),this.get_label("nosubject"));
if(!_da&&_da!==""){
_d7.focus();
return false;
}else{
_d7.value=_da?_da:this.get_label("nosubject");
}
}
if((!window.tinyMCE||!tinyMCE.get("compose-body"))&&_d8.value==""&&!confirm(this.get_label("nobodywarning"))){
_d8.focus();
return false;
}else{
if(window.tinyMCE&&tinyMCE.get("compose-body")&&!tinyMCE.get("compose-body").getContent()&&!confirm(this.get_label("nobodywarning"))){
tinyMCE.get("compose-body").focus();
return false;
}
}
this.stop_spellchecking();
return true;
};
this.stop_spellchecking=function(){
if(this.env.spellcheck&&!this.spellcheck_ready){
exec_event(this.env.spellcheck.check_link,"click");
this.set_spellcheck_state("ready");
}
};
this.display_spellcheck_controls=function(vis){
if(this.env.spellcheck){
if(!vis){
this.stop_spellchecking();
}
this.env.spellcheck.check_link.style.visibility=vis?"visible":"hidden";
this.env.spellcheck.switch_lan_pic.style.visibility=vis?"visible":"hidden";
}
};
this.set_spellcheck_state=function(s){
this.spellcheck_ready=(s=="check_spelling"||s=="ready");
this.enable_command("spellcheck",this.spellcheck_ready);
};
this.set_draft_id=function(id){
var f;
if(f=rcube_find_object("_draft_saveid")){
f.value=id;
}
};
this.auto_save_start=function(){
if(this.env.draft_autosave){
this.save_timer=self.setTimeout(function(){
_1.command("savedraft");
},this.env.draft_autosave*1000);
}
this.busy=false;
};
this.compose_field_hash=function(_df){
var _e0=rcube_find_object("_to");
var _e1=rcube_find_object("_cc");
var _e2=rcube_find_object("_bcc");
var _e3=rcube_find_object("_subject");
var _e4,_e5;
var str="";
if(_e0&&_e0.value){
str+=_e0.value+":";
}
if(_e1&&_e1.value){
str+=_e1.value+":";
}
if(_e2&&_e2.value){
str+=_e2.value+":";
}
if(_e3&&_e3.value){
str+=_e3.value+":";
}
if(_e4=tinyMCE.get("compose-body")){
str+=_e4.getContent();
}else{
_e5=rcube_find_object("_message");
str+=_e5.value;
}
if(_df){
this.cmp_hash=str;
}
return str;
};
this.change_identity=function(obj){
if(!obj||!obj.options){
return false;
}
var id=obj.options[obj.selectedIndex].value;
var _e9=rcube_find_object("_message");
var _ea=_e9?_e9.value:"";
var _eb=(rcube_find_object("_is_html").value=="1");
var sig,p;
if(!this.env.identity){
this.env.identity=id;
}
if(!_eb){
if(this.env.identity&&this.env.signatures&&this.env.signatures[this.env.identity]){
if(this.env.signatures[this.env.identity]["is_html"]){
sig=this.env.signatures[this.env.identity]["plain_text"];
}else{
sig=this.env.signatures[this.env.identity]["text"];
}
if(sig.indexOf("-- ")!=0){
sig="-- \n"+sig;
}
p=_ea.lastIndexOf(sig);
if(p>=0){
_ea=_ea.substring(0,p-1)+_ea.substring(p+sig.length,_ea.length);
}
}
_ea=_ea.replace(/[\r\n]+$/,"");
if(this.env.signatures&&this.env.signatures[id]){
sig=this.env.signatures[id]["text"];
if(this.env.signatures[id]["is_html"]){
sig=this.env.signatures[id]["plain_text"];
}
if(sig.indexOf("-- ")!=0){
sig="-- \n"+sig;
}
_ea+="\n\n"+sig;
}
}else{
var _ee=tinyMCE.get("compose-body");
if(this.env.signatures){
var _ef=_ee.dom.get("_rc_sig");
var _f0="";
var _f1=true;
if(!_ef){
if(bw.ie){
_ee.getBody().appendChild(_ee.getDoc().createElement("br"));
}
_ef=_ee.getDoc().createElement("div");
_ef.setAttribute("id","_rc_sig");
_ee.getBody().appendChild(_ef);
}
if(this.env.signatures[id]){
_f0=this.env.signatures[id]["text"];
_f1=this.env.signatures[id]["is_html"];
}
if(_f1){
_ef.innerHTML=_f0;
}else{
_ef.innerHTML="<pre>"+_f0+"</pre>";
}
}
}
if(_e9){
_e9.value=_ea;
}
this.env.identity=id;
return true;
};
this.show_attachment_form=function(a){
if(!this.gui_objects.uploadbox){
return false;
}
var elm,_f4;
if(elm=this.gui_objects.uploadbox){
if(a&&(_f4=this.gui_objects.attachmentlist)){
var pos=rcube_get_object_pos(_f4);
var _f6=pos.x;
var top=pos.y+_f4.offsetHeight+10;
elm.style.top=top+"px";
elm.style.left=_f6+"px";
}
elm.style.visibility=a?"visible":"hidden";
}
try{
if(!a&&this.gui_objects.attachmentform!=this.gui_objects.messageform){
this.gui_objects.attachmentform.reset();
}
}
catch(e){
}
return true;
};
this.upload_file=function(_f8){
if(!_f8){
return false;
}
var _f9=false;
for(var n=0;n<_f8.elements.length;n++){
if(_f8.elements[n].type=="file"&&_f8.elements[n].value){
_f9=true;
break;
}
}
if(_f9){
var ts=new Date().getTime();
var _fc="rcmupload"+ts;
if(document.all){
var _fd="<iframe name=\""+_fc+"\" src=\"program/blank.gif\" style=\"width:0;height:0;visibility:hidden;\"></iframe>";
document.body.insertAdjacentHTML("BeforeEnd",_fd);
}else{
var _fe=document.createElement("IFRAME");
_fe.name=_fc;
_fe.style.border="none";
_fe.style.width=0;
_fe.style.height=0;
_fe.style.visibility="hidden";
document.body.appendChild(_fe);
}
_f8.target=_fc;
_f8.action=this.env.comm_path+"&_action=upload";
_f8.setAttribute("enctype","multipart/form-data");
_f8.submit();
}
this.gui_objects.attachmentform=_f8;
return true;
};
this.add2attachment_list=function(_ff,_100){
if(!this.gui_objects.attachmentlist){
return false;
}
var li=document.createElement("LI");
li.id=_ff;
li.innerHTML=_100;
this.gui_objects.attachmentlist.appendChild(li);
return true;
};
this.remove_from_attachment_list=function(name){
if(!this.gui_objects.attachmentlist){
return false;
}
var list=this.gui_objects.attachmentlist.getElementsByTagName("li");
for(i=0;i<list.length;i++){
if(list[i].id==name){
this.gui_objects.attachmentlist.removeChild(list[i]);
}
}
};
this.remove_attachment=function(name){
if(name){
this.http_post("remove-attachment","_file="+urlencode(name));
}
return true;
};
this.add_contact=function(_105){
if(_105){
this.http_post("addcontact","_address="+_105);
}
return true;
};
this.qsearch=function(_106,_107){
if(_106!=""){
if(this.message_list){
this.message_list.clear();
}else{
if(this.contact_list){
this.contact_list.clear(true);
this.show_contentframe(false);
}
}
if(this.gui_objects.search_filter){
_107="&_filter="+this.gui_objects.search_filter.value;
}
this.env.current_page=1;
this.set_busy(true,"searching");
this.http_request("search","_q="+urlencode(_106)+(this.env.mailbox?"&_mbox="+urlencode(this.env.mailbox):"")+(this.env.source?"&_source="+urlencode(this.env.source):"")+(_107?_107:""),true);
}
return true;
};
this.reset_qsearch=function(){
if(this.gui_objects.qsearchbox){
this.gui_objects.qsearchbox.value="";
}
this.env.search_request=null;
return true;
};
this.sent_successfully=function(type,msg){
this.list_mailbox();
this.display_message(msg,type,true);
};
this.ksearch_keypress=function(e,obj){
if(this.ksearch_timer){
clearTimeout(this.ksearch_timer);
}
var _10c;
var key=rcube_event.get_keycode(e);
var mod=rcube_event.get_modifier(e);
switch(key){
case 38:
case 40:
if(!this.ksearch_pane){
break;
}
var dir=key==38?1:0;
_10c=document.getElementById("rcmksearchSelected");
if(!_10c){
_10c=this.ksearch_pane.ul.firstChild;
}
if(_10c){
this.ksearch_select(dir?_10c.previousSibling:_10c.nextSibling);
}
return rcube_event.cancel(e);
case 9:
if(mod==SHIFT_KEY){
break;
}
case 13:
if(this.ksearch_selected===null||!this.ksearch_input||!this.ksearch_value){
break;
}
this.insert_recipient(this.ksearch_selected);
this.ksearch_hide();
return rcube_event.cancel(e);
case 27:
this.ksearch_hide();
break;
}
this.ksearch_timer=window.setTimeout(function(){
_1.ksearch_get_results();
},200);
this.ksearch_input=obj;
return true;
};
this.ksearch_select=function(node){
var _111=document.getElementById("rcmksearchSelected");
if(_111&&node){
_111.removeAttribute("id");
this.set_classname(_111,"selected",false);
}
if(node){
node.setAttribute("id","rcmksearchSelected");
this.set_classname(node,"selected",true);
this.ksearch_selected=node._rcm_id;
}
};
this.insert_recipient=function(id){
if(!this.env.contacts[id]||!this.ksearch_input){
return;
}
var _113=this.ksearch_input.value.toLowerCase();
var cpos=this.get_caret_pos(this.ksearch_input);
var p=_113.lastIndexOf(this.ksearch_value,cpos);
var pre=this.ksearch_input.value.substring(0,p);
var end=this.ksearch_input.value.substring(p+this.ksearch_value.length,this.ksearch_input.value.length);
var _118=this.env.contacts[id]+", ";
this.ksearch_input.value=pre+_118+end;
cpos=p+_118.length;
if(this.ksearch_input.setSelectionRange){
this.ksearch_input.setSelectionRange(cpos,cpos);
}
};
this.ksearch_get_results=function(){
var _119=this.ksearch_input?this.ksearch_input.value:null;
if(_119===null){
return;
}
if(this.ksearch_pane&&this.ksearch_pane.visible){
this.ksearch_pane.show(0);
}
var cpos=this.get_caret_pos(this.ksearch_input);
var p=_119.lastIndexOf(",",cpos-1);
var q=_119.substring(p+1,cpos);
q=q.replace(/(^\s+|\s+$)/g,"").toLowerCase();
if(!q.length||q==this.ksearch_value){
return;
}
this.ksearch_value=q;
this.display_message(this.get_label("searching"),"loading",true);
this.http_post("autocomplete","_search="+q);
};
this.ksearch_query_results=function(_11d){
this.hide_message();
this.env.contacts=_11d?_11d:[];
var _11e=new Array();
var c=0;
for(var i=0;i<this.env.contacts.length;i++){
_11e[c++]=i;
if(c==15){
break;
}
}
this.ksearch_display_results(this.env.contacts,_11e,c);
};
this.ksearch_display_results=function(_121,_122,c){
if(c&&_121.length&&this.ksearch_input){
var p,ul,li;
if(!this.ksearch_pane){
ul=document.createElement("UL");
this.ksearch_pane=new rcube_layer("rcmKSearchpane",{vis:0,zindex:30000});
this.ksearch_pane.elm.appendChild(ul);
this.ksearch_pane.ul=ul;
}else{
ul=this.ksearch_pane.ul;
}
ul.innerHTML="";
for(i=0;i<_121.length;i++){
li=document.createElement("LI");
li.innerHTML=_121[i].replace(new RegExp("("+this.ksearch_value+")","ig"),"##$1%%").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/##([^%]+)%%/g,"<b>$1</b>");
li.onmouseover=function(){
_1.ksearch_select(this);
};
li.onmouseup=function(){
_1.ksearch_click(this);
};
li._rcm_id=_122[i];
ul.appendChild(li);
}
if(this.ksearch_selected!==null){
p=find_in_array(this.ksearch_selected,_122);
if(p>=0&&ul.childNodes){
ul.childNodes[p].setAttribute("id","rcmksearchSelected");
this.set_classname(ul.childNodes[p],"selected",true);
}else{
this.ksearch_selected=null;
}
}
if(this.ksearch_selected===null){
ul.firstChild.setAttribute("id","rcmksearchSelected");
this.set_classname(ul.firstChild,"selected",true);
this.ksearch_selected=_122[0];
}
var pos=rcube_get_object_pos(this.ksearch_input);
this.ksearch_pane.move(pos.x,pos.y+this.ksearch_input.offsetHeight);
this.ksearch_pane.show(1);
}else{
this.ksearch_hide();
}
};
this.ksearch_click=function(node){
this.insert_recipient(node._rcm_id);
this.ksearch_hide();
if(_1.ksearch_input){
this.ksearch_input.focus();
}
};
this.ksearch_blur=function(){
if(this.ksearch_timer){
clearTimeout(this.ksearch_timer);
}
this.ksearch_value="";
this.ksearch_input=null;
this.ksearch_hide();
};
this.ksearch_hide=function(){
this.ksearch_selected=null;
if(this.ksearch_pane){
this.ksearch_pane.show(0);
}
};
this.contactlist_keypress=function(list){
if(list.key_pressed==list.DELETE_KEY){
this.command("delete");
}
};
this.contactlist_select=function(list){
if(this.preview_timer){
clearTimeout(this.preview_timer);
}
var id,_12c,_1=this;
if(id=list.get_single_selection()){
this.preview_timer=window.setTimeout(function(){
_1.load_contact(id,"show");
},200);
}else{
if(this.env.contentframe){
this.show_contentframe(false);
}
}
this.enable_command("compose",list.selection.length>0);
this.enable_command("edit",(id&&this.env.address_sources&&!this.env.address_sources[this.env.source].readonly)?true:false);
this.enable_command("delete",list.selection.length&&this.env.address_sources&&!this.env.address_sources[this.env.source].readonly);
return false;
};
this.list_contacts=function(src,page){
var _12f="";
var _130=window;
if(!src){
src=this.env.source;
}
if(page&&this.current_page==page&&src==this.env.source){
return false;
}
if(src!=this.env.source){
page=1;
this.env.current_page=page;
this.reset_qsearch();
}
this.select_folder(src,this.env.source);
this.env.source=src;
if(this.gui_objects.contactslist){
this.list_contacts_remote(src,page);
return;
}
if(this.env.contentframe&&window.frames&&window.frames[this.env.contentframe]){
_130=window.frames[this.env.contentframe];
_12f="&_framed=1";
}
if(this.env.search_request){
_12f+="&_search="+this.env.search_request;
}
this.set_busy(true,"loading");
_130.location.href=this.env.comm_path+(src?"&_source="+urlencode(src):"")+(page?"&_page="+page:"")+_12f;
};
this.list_contacts_remote=function(src,page){
this.contact_list.clear(true);
this.show_contentframe(false);
this.enable_command("delete","compose",false);
var url=(src?"_source="+urlencode(src):"")+(page?(src?"&":"")+"_page="+page:"");
this.env.source=src;
if(this.env.search_request){
url+="&_search="+this.env.search_request;
}
this.set_busy(true,"loading");
this.http_request("list",url,true);
};
this.load_contact=function(cid,_135,_136){
var _137="";
var _138=window;
if(this.env.contentframe&&window.frames&&window.frames[this.env.contentframe]){
_137="&_framed=1";
_138=window.frames[this.env.contentframe];
this.show_contentframe(true);
}else{
if(_136){
return false;
}
}
if(_135&&(cid||_135=="add")&&!this.drag_active){
this.set_busy(true);
_138.location.href=this.env.comm_path+"&_action="+_135+"&_source="+urlencode(this.env.source)+"&_cid="+urlencode(cid)+_137;
}
return true;
};
this.copy_contact=function(cid,to){
if(!cid){
cid=this.contact_list.get_selection().join(",");
}
if(to!=this.env.source&&cid&&this.env.address_sources[to]&&!this.env.address_sources[to].readonly){
this.http_post("copy","_cid="+urlencode(cid)+"&_source="+urlencode(this.env.source)+"&_to="+urlencode(to));
}
};
this.delete_contacts=function(){
var _13b=this.contact_list.get_selection();
if(!(_13b.length||this.env.cid)||!confirm(this.get_label("deletecontactconfirm"))){
return;
}
var _13c=new Array();
var qs="";
if(this.env.cid){
_13c[_13c.length]=this.env.cid;
}else{
var id;
for(var n=0;n<_13b.length;n++){
id=_13b[n];
_13c[_13c.length]=id;
this.contact_list.remove_row(id,(n==_13b.length-1));
}
if(_13b.length==1){
this.show_contentframe(false);
}
}
if(this.env.search_request){
qs+="&_search="+this.env.search_request;
}
this.http_post("delete","_cid="+urlencode(_13c.join(","))+"&_source="+urlencode(this.env.source)+"&_from="+(this.env.action?this.env.action:"")+qs);
return true;
};
this.update_contact_row=function(cid,_141){
var row;
if(this.contact_list.rows[cid]&&(row=this.contact_list.rows[cid].obj)){
for(var c=0;c<_141.length;c++){
if(row.cells[c]){
row.cells[c].innerHTML=_141[c];
}
}
return true;
}
return false;
};
this.init_subscription_list=function(){
var p=this;
this.subscription_list=new rcube_list_widget(this.gui_objects.subscriptionlist,{multiselect:false,draggable:true,keyboard:false,toggleselect:true});
this.subscription_list.addEventListener("select",function(o){
p.subscription_select(o);
});
this.subscription_list.addEventListener("dragstart",function(o){
p.drag_active=true;
});
this.subscription_list.addEventListener("dragend",function(o){
p.subscription_move_folder(o);
});
this.subscription_list.row_init=function(row){
var _149=row.obj.getElementsByTagName("A");
if(_149[0]){
_149[0].onclick=function(){
p.rename_folder(row.id);
return false;
};
}
if(_149[1]){
_149[1].onclick=function(){
p.delete_folder(row.id);
return false;
};
}
row.obj.onmouseover=function(){
p.focus_subscription(row.id);
};
row.obj.onmouseout=function(){
p.unfocus_subscription(row.id);
};
};
this.subscription_list.init();
};
this.identity_select=function(list){
var id;
if(id=list.get_single_selection()){
this.load_identity(id,"edit-identity");
}
};
this.load_identity=function(id,_14d){
if(_14d=="edit-identity"&&(!id||id==this.env.iid)){
return false;
}
var _14e="";
var _14f=window;
if(this.env.contentframe&&window.frames&&window.frames[this.env.contentframe]){
_14e="&_framed=1";
_14f=window.frames[this.env.contentframe];
document.getElementById(this.env.contentframe).style.visibility="inherit";
}
if(_14d&&(id||_14d=="add-identity")){
this.set_busy(true);
_14f.location.href=this.env.comm_path+"&_action="+_14d+"&_iid="+id+_14e;
}
return true;
};
this.delete_identity=function(id){
var _151=this.identity_list.get_selection();
if(!(_151.length||this.env.iid)){
return;
}
if(!id){
id=this.env.iid?this.env.iid:_151[0];
}
this.goto_url("delete-identity","_iid="+id,true);
return true;
};
this.focus_subscription=function(id){
var row,_154;
var reg=RegExp("["+RegExp.escape(this.env.delimiter)+"]?[^"+RegExp.escape(this.env.delimiter)+"]+$");
if(this.drag_active&&this.env.folder&&(row=document.getElementById(id))){
if(this.env.subscriptionrows[id]&&(_154=this.env.subscriptionrows[id][0])){
if(this.check_droptarget(_154)&&!this.env.subscriptionrows[this.get_folder_row_id(this.env.folder)][2]&&(_154!=this.env.folder.replace(reg,""))&&(!_154.match(new RegExp("^"+RegExp.escape(this.env.folder+this.env.delimiter))))){
this.set_env("dstfolder",_154);
this.set_classname(row,"droptarget",true);
}
}else{
if(this.env.folder.match(new RegExp(RegExp.escape(this.env.delimiter)))){
this.set_env("dstfolder",this.env.delimiter);
this.set_classname(this.subscription_list.frame,"droptarget",true);
}
}
}
};
this.unfocus_subscription=function(id){
var row;
this.set_env("dstfolder",null);
if(this.env.subscriptionrows[id]&&(row=document.getElementById(id))){
this.set_classname(row,"droptarget",false);
}else{
this.set_classname(this.subscription_list.frame,"droptarget",false);
}
};
this.subscription_select=function(list){
var id,_15a;
if((id=list.get_single_selection())&&this.env.subscriptionrows["rcmrow"+id]&&(_15a=this.env.subscriptionrows["rcmrow"+id][0])){
this.set_env("folder",_15a);
}else{
this.set_env("folder",null);
}
if(this.gui_objects.createfolderhint){
this.gui_objects.createfolderhint.innerHTML=this.env.folder?this.get_label("addsubfolderhint"):"";
}
};
this.subscription_move_folder=function(list){
var reg=RegExp("["+RegExp.escape(this.env.delimiter)+"]?[^"+RegExp.escape(this.env.delimiter)+"]+$");
if(this.env.folder&&this.env.dstfolder&&(this.env.dstfolder!=this.env.folder)&&(this.env.dstfolder!=this.env.folder.replace(reg,""))){
var reg=new RegExp("[^"+RegExp.escape(this.env.delimiter)+"]*["+RegExp.escape(this.env.delimiter)+"]","g");
var _15d=this.env.folder.replace(reg,"");
var _15e=this.env.dstfolder==this.env.delimiter?_15d:this.env.dstfolder+this.env.delimiter+_15d;
this.set_busy(true,"foldermoving");
this.http_post("rename-folder","_folder_oldname="+urlencode(this.env.folder)+"&_folder_newname="+urlencode(_15e),true);
}
this.drag_active=false;
this.unfocus_subscription(this.get_folder_row_id(this.env.dstfolder));
};
this.create_folder=function(name){
if(this.edit_folder){
this.reset_folder_rename();
}
var form;
if((form=this.gui_objects.editform)&&form.elements["_folder_name"]){
name=form.elements["_folder_name"].value;
if(name.indexOf(this.env.delimiter)>=0){
alert(this.get_label("forbiddencharacter")+" ("+this.env.delimiter+")");
return false;
}
if(this.env.folder&&name!=""){
name=this.env.folder+this.env.delimiter+name;
}
this.set_busy(true,"foldercreating");
this.http_post("create-folder","_name="+urlencode(name),true);
}else{
if(form.elements["_folder_name"]){
form.elements["_folder_name"].focus();
}
}
};
this.rename_folder=function(id){
var temp,row,form;
if(temp=this.edit_folder){
this.reset_folder_rename();
if(temp==id){
return;
}
}
if(id&&this.env.subscriptionrows[id]&&(row=document.getElementById(id))){
var reg=new RegExp(".*["+RegExp.escape(this.env.delimiter)+"]");
this.name_input=document.createElement("INPUT");
this.name_input.value=this.env.subscriptionrows[id][0].replace(reg,"");
this.name_input.style.width="100%";
reg=new RegExp("["+RegExp.escape(this.env.delimiter)+"]?[^"+RegExp.escape(this.env.delimiter)+"]+$");
this.name_input.__parent=this.env.subscriptionrows[id][0].replace(reg,"");
this.name_input.onkeypress=function(e){
rcmail.name_input_keypress(e);
};
row.cells[0].replaceChild(this.name_input,row.cells[0].firstChild);
this.edit_folder=id;
this.name_input.select();
if(form=this.gui_objects.editform){
form.onsubmit=function(){
return false;
};
}
}
};
this.reset_folder_rename=function(){
var cell=this.name_input?this.name_input.parentNode:null;
if(cell&&this.edit_folder&&this.env.subscriptionrows[this.edit_folder]){
cell.innerHTML=this.env.subscriptionrows[this.edit_folder][1];
}
this.edit_folder=null;
};
this.name_input_keypress=function(e){
var key=rcube_event.get_keycode(e);
if(key==13){
var _16a=this.name_input?this.name_input.value:null;
if(this.edit_folder&&_16a){
if(_16a.indexOf(this.env.delimiter)>=0){
alert(this.get_label("forbiddencharacter")+" ("+this.env.delimiter+")");
return false;
}
if(this.name_input.__parent){
_16a=this.name_input.__parent+this.env.delimiter+_16a;
}
this.set_busy(true,"folderrenaming");
this.http_post("rename-folder","_folder_oldname="+urlencode(this.env.subscriptionrows[this.edit_folder][0])+"&_folder_newname="+urlencode(_16a),true);
}
}else{
if(key==27){
this.reset_folder_rename();
}
}
};
this.delete_folder=function(id){
var _16c=this.env.subscriptionrows[id][0];
if(this.edit_folder){
this.reset_folder_rename();
}
if(_16c&&confirm(this.get_label("deletefolderconfirm"))){
this.set_busy(true,"folderdeleting");
this.http_post("delete-folder","_mboxes="+urlencode(_16c),true);
this.set_env("folder",null);
if(this.gui_objects.createfolderhint){
this.gui_objects.createfolderhint.innerHTML="";
}
}
};
this.add_folder_row=function(name,_16e,_16f,_170){
if(!this.gui_objects.subscriptionlist){
return false;
}
for(var _171 in this.env.subscriptionrows){
if(this.env.subscriptionrows[_171]!=null&&!this.env.subscriptionrows[_171][2]){
break;
}
}
var _172,form;
var _174=this.gui_objects.subscriptionlist.tBodies[0];
var id="rcmrow"+(_174.childNodes.length+1);
var _176=this.subscription_list.get_single_selection();
if(_16f&&_16f.id){
id=_16f.id;
_171=_16f.id;
}
if(!id||!(_172=document.getElementById(_171))){
this.goto_url("folders");
}else{
var row=this.clone_table_row(_172);
row.id=id;
if(_170&&(_170=this.get_folder_row_id(_170))){
_174.insertBefore(row,document.getElementById(_170));
}else{
_174.appendChild(row);
}
if(_16f){
_174.removeChild(_16f);
}
}
this.env.subscriptionrows[row.id]=[name,_16e,0];
row.cells[0].innerHTML=_16e;
if(!_16f){
row.cells[1].innerHTML="*";
}
if(!_16f&&row.cells[2]&&row.cells[2].firstChild.tagName=="INPUT"){
row.cells[2].firstChild.value=name;
row.cells[2].firstChild.checked=true;
}
if(!_16f&&(form=this.gui_objects.editform)){
if(form.elements["_folder_oldname"]){
form.elements["_folder_oldname"].options[form.elements["_folder_oldname"].options.length]=new Option(name,name);
}
if(form.elements["_folder_name"]){
form.elements["_folder_name"].value="";
}
}
this.init_subscription_list();
if(_176&&document.getElementById("rcmrow"+_176)){
this.subscription_list.select_row(_176);
}
if(document.getElementById(id).scrollIntoView){
document.getElementById(id).scrollIntoView();
}
};
this.replace_folder_row=function(_178,_179,_17a,_17b){
var id=this.get_folder_row_id(_178);
var row=document.getElementById(id);
this.add_folder_row(_179,_17a,row,_17b);
var form,elm;
if((form=this.gui_objects.editform)&&(elm=form.elements["_folder_oldname"])){
for(var i=0;i<elm.options.length;i++){
if(elm.options[i].value==_178){
elm.options[i].text=_17a;
elm.options[i].value=_179;
break;
}
}
form.elements["_folder_newname"].value="";
}
};
this.remove_folder_row=function(_181){
var row;
var id=this.get_folder_row_id(_181);
if(id&&(row=document.getElementById(id))){
row.style.display="none";
}
var form;
if((form=this.gui_objects.editform)&&form.elements["_folder_oldname"]){
for(var i=0;i<form.elements["_folder_oldname"].options.length;i++){
if(form.elements["_folder_oldname"].options[i].value==_181){
form.elements["_folder_oldname"].options[i]=null;
break;
}
}
}
if(form&&form.elements["_folder_newname"]){
form.elements["_folder_newname"].value="";
}
};
this.subscribe_folder=function(_186){
if(_186){
this.http_post("subscribe","_mbox="+urlencode(_186));
}
};
this.unsubscribe_folder=function(_187){
if(_187){
this.http_post("unsubscribe","_mbox="+urlencode(_187));
}
};
this.get_folder_row_id=function(_188){
for(var id in this.env.subscriptionrows){
if(this.env.subscriptionrows[id]&&this.env.subscriptionrows[id][0]==_188){
break;
}
}
return id;
};
this.clone_table_row=function(row){
var cell,td;
var _18d=document.createElement("TR");
for(var n=0;n<row.cells.length;n++){
cell=row.cells[n];
td=document.createElement("TD");
if(cell.className){
td.className=cell.className;
}
if(cell.align){
td.setAttribute("align",cell.align);
}
td.innerHTML=cell.innerHTML;
_18d.appendChild(td);
}
return _18d;
};
this.set_page_buttons=function(){
this.enable_command("nextpage",(this.env.pagecount>this.env.current_page));
this.enable_command("lastpage",(this.env.pagecount>this.env.current_page));
this.enable_command("previouspage",(this.env.current_page>1));
this.enable_command("firstpage",(this.env.current_page>1));
};
this.set_button=function(_18f,_190){
var _191=this.buttons[_18f];
var _192,obj;
if(!_191||!_191.length){
return false;
}
for(var n=0;n<_191.length;n++){
_192=_191[n];
obj=document.getElementById(_192.id);
if(obj&&_192.type=="image"&&!_192.status){
_192.pas=obj._original_src?obj._original_src:obj.src;
if(obj.runtimeStyle&&obj.runtimeStyle.filter&&obj.runtimeStyle.filter.match(/src=['"]([^'"]+)['"]/)){
_192.pas=RegExp.$1;
}
}else{
if(obj&&!_192.status){
_192.pas=String(obj.className);
}
}
if(obj&&_192.type=="image"&&_192[_190]){
_192.status=_190;
obj.src=_192[_190];
}else{
if(obj&&typeof (_192[_190])!="undefined"){
_192.status=_190;
obj.className=_192[_190];
}
}
if(obj&&_192.type=="input"){
_192.status=_190;
obj.disabled=!_190;
}
}
};
this.set_alttext=function(_195,_196){
if(!this.buttons[_195]||!this.buttons[_195].length){
return;
}
var _197,obj,link;
for(var n=0;n<this.buttons[_195].length;n++){
_197=this.buttons[_195][n];
obj=document.getElementById(_197.id);
if(_197.type=="image"&&obj){
obj.setAttribute("alt",this.get_label(_196));
if((link=obj.parentNode)&&link.tagName=="A"){
link.setAttribute("title",this.get_label(_196));
}
}else{
if(obj){
obj.setAttribute("title",this.get_label(_196));
}
}
}
};
this.button_over=function(_19b,id){
var _19d=this.buttons[_19b];
var _19e,img;
if(!_19d||!_19d.length){
return false;
}
for(var n=0;n<_19d.length;n++){
_19e=_19d[n];
if(_19e.id==id&&_19e.status=="act"){
img=document.getElementById(_19e.id);
if(img&&_19e.over){
img.src=_19e.over;
}
}
}
};
this.button_sel=function(_1a1,id){
var _1a3=this.buttons[_1a1];
var _1a4,img;
if(!_1a3||!_1a3.length){
return;
}
for(var n=0;n<_1a3.length;n++){
_1a4=_1a3[n];
if(_1a4.id==id&&_1a4.status=="act"){
img=document.getElementById(_1a4.id);
if(img&&_1a4.sel){
img.src=_1a4.sel;
}
}
}
};
this.button_out=function(_1a7,id){
var _1a9=this.buttons[_1a7];
var _1aa,img;
if(!_1a9||!_1a9.length){
return;
}
for(var n=0;n<_1a9.length;n++){
_1aa=_1a9[n];
if(_1aa.id==id&&_1aa.status=="act"){
img=document.getElementById(_1aa.id);
if(img&&_1aa.act){
img.src=_1aa.act;
}
}
}
};
this.set_classname=function(obj,_1ae,set){
var reg=new RegExp("s*"+_1ae,"i");
if(!set&&obj.className.match(reg)){
obj.className=obj.className.replace(reg,"");
}else{
if(set&&!obj.className.match(reg)){
obj.className+=" "+_1ae;
}
}
};
this.set_pagetitle=function(_1b1){
if(_1b1&&document.title){
document.title=_1b1;
}
};
this.display_message=function(msg,type,hold){
if(!this.loaded){
this.pending_message=new Array(msg,type);
return true;
}
if(this.env.framed&&parent.rcmail){
return parent.rcmail.display_message(msg,type,hold);
}
if(!this.gui_objects.message){
return false;
}
if(this.message_timer){
clearTimeout(this.message_timer);
}
var cont=msg;
if(type){
cont="<div class=\""+type+"\">"+cont+"</div>";
}
var _1b6=this;
this.gui_objects.message.innerHTML=cont;
this.gui_objects.message.style.display="block";
if(type!="loading"){
this.gui_objects.message.onmousedown=function(){
_1b6.hide_message();
return true;
};
}
if(!hold){
this.message_timer=window.setTimeout(function(){
_1.hide_message();
},this.message_time);
}
};
this.hide_message=function(){
if(this.gui_objects.message){
this.gui_objects.message.style.display="none";
this.gui_objects.message.onmousedown=null;
}
};
this.select_folder=function(name,old){
if(this.gui_objects.folderlist){
var _1b9,_1ba;
if((_1b9=this.get_folder_li(old))){
this.set_classname(_1b9,"selected",false);
this.set_classname(_1b9,"unfocused",false);
}
if((_1ba=this.get_folder_li(name))){
this.set_classname(_1ba,"unfocused",false);
this.set_classname(_1ba,"selected",true);
}
}
};
this.get_folder_li=function(name){
if(this.gui_objects.folderlist){
name=String(name).replace(this.identifier_expr,"");
return document.getElementById("rcmli"+name);
}
return null;
};
this.set_message_coltypes=function(_1bc){
this.coltypes=_1bc;
var cell,col;
var _1bf=this.gui_objects.messagelist?this.gui_objects.messagelist.tHead:null;
for(var n=0;_1bf&&n<this.coltypes.length;n++){
col=this.coltypes[n];
if((cell=_1bf.rows[0].cells[n+1])&&(col=="from"||col=="to")){
if(cell.firstChild&&cell.firstChild.tagName=="A"){
cell.firstChild.innerHTML=this.get_label(this.coltypes[n]);
cell.firstChild.onclick=function(){
return rcmail.command("sort",this.__col,this);
};
cell.firstChild.__col=col;
}else{
cell.innerHTML=this.get_label(this.coltypes[n]);
}
cell.id="rcm"+col;
}else{
if(col=="subject"&&this.message_list){
this.message_list.subject_col=n+1;
}
}
}
};
this.add_message_row=function(uid,cols,_1c3,_1c4,_1c5){
if(!this.gui_objects.messagelist||!this.message_list){
return false;
}
var _1c6=this.gui_objects.messagelist.tBodies[0];
var _1c7=_1c6.rows.length;
var even=_1c7%2;
this.env.messages[uid]={deleted:_1c3.deleted?1:0,replied:_1c3.replied?1:0,unread:_1c3.unread?1:0,forwarded:_1c3.forwarded?1:0,flagged:_1c3.flagged?1:0};
var row=document.createElement("TR");
row.id="rcmrow"+uid;
row.className="message"+(even?" even":" odd")+(_1c3.unread?" unread":"")+(_1c3.deleted?" deleted":"")+(_1c3.flagged?" flagged":"");
if(this.message_list.in_selection(uid)){
row.className+=" selected";
}
var icon=this.env.messageicon;
if(_1c3.deleted&&this.env.deletedicon){
icon=this.env.deletedicon;
}else{
if(_1c3.replied&&this.env.repliedicon){
if(_1c3.forwarded&&this.env.forwardedrepliedicon){
icon=this.env.forwardedrepliedicon;
}else{
icon=this.env.repliedicon;
}
}else{
if(_1c3.forwarded&&this.env.forwardedicon){
icon=this.env.forwardedicon;
}else{
if(_1c3.unread&&this.env.unreadicon){
icon=this.env.unreadicon;
}
}
}
}
var col=document.createElement("TD");
col.className="icon";
col.innerHTML=icon?"<img src=\""+icon+"\" alt=\"\" />":"";
row.appendChild(col);
for(var n=0;n<this.coltypes.length;n++){
var c=this.coltypes[n];
col=document.createElement("TD");
col.className=String(c).toLowerCase();
if(c=="flag"){
if(_1c3.flagged&&this.env.flaggedicon){
col.innerHTML="<img src=\""+this.env.flaggedicon+"\" alt=\"\" />";
}else{
if(!_1c3.flagged&&this.env.unflaggedicon){
col.innerHTML="<img src=\""+this.env.unflaggedicon+"\" alt=\"\" />";
}
}
}else{
if(c=="attachment"){
col.innerHTML=_1c4&&this.env.attachmenticon?"<img src=\""+this.env.attachmenticon+"\" alt=\"\" />":"&nbsp;";
}else{
col.innerHTML=cols[c];
}
}
row.appendChild(col);
}
this.message_list.insert_row(row,_1c5);
if(_1c5&&this.env.pagesize&&this.message_list.rowcount>this.env.pagesize){
var uid=this.message_list.get_last_row();
this.message_list.remove_row(uid);
this.message_list.clear_selection(uid);
}
};
this.set_rowcount=function(text){
if(this.gui_objects.countdisplay){
this.gui_objects.countdisplay.innerHTML=text;
}
this.set_page_buttons();
};
this.set_mailboxname=function(_1cf){
if(this.gui_objects.mailboxname&&_1cf){
this.gui_objects.mailboxname.innerHTML=_1cf;
}
};
this.set_quota=function(_1d0){
if(this.gui_objects.quotadisplay&&_1d0){
this.gui_objects.quotadisplay.innerHTML=_1d0;
}
};
this.set_unread_count=function(mbox,_1d2,_1d3){
if(!this.gui_objects.mailboxlist){
return false;
}
this.env.unread_counts[mbox]=_1d2;
this.set_unread_count_display(mbox,_1d3);
};
this.set_unread_count_display=function(mbox,_1d5){
var reg,_1d7,item,_1d9,_1da,div;
if(item=this.get_folder_li(mbox)){
_1d9=this.env.unread_counts[mbox]?this.env.unread_counts[mbox]:0;
_1d7=item.getElementsByTagName("a")[0];
reg=/\s+\([0-9]+\)$/i;
_1da=0;
if((div=item.getElementsByTagName("div")[0])&&div.className.match(/collapsed/)){
for(var k in this.env.unread_counts){
if(k.indexOf(mbox+this.env.delimiter)==0){
_1da+=this.env.unread_counts[k];
}
}
}
if(_1d9&&_1d7.innerHTML.match(reg)){
_1d7.innerHTML=_1d7.innerHTML.replace(reg," ("+_1d9+")");
}else{
if(_1d9){
_1d7.innerHTML+=" ("+_1d9+")";
}else{
_1d7.innerHTML=_1d7.innerHTML.replace(reg,"");
}
}
reg=new RegExp(RegExp.escape(this.env.delimiter)+"[^"+RegExp.escape(this.env.delimiter)+"]+$");
if(mbox.match(reg)){
this.set_unread_count_display(mbox.replace(reg,""),false);
}
this.set_classname(item,"unread",(_1d9+_1da)>0?true:false);
}
reg=/^\([0-9]+\)\s+/i;
if(_1d5&&document.title){
var _1dd=String(document.title);
var _1de="";
if(_1d9&&_1dd.match(reg)){
_1de=_1dd.replace(reg,"("+_1d9+") ");
}else{
if(_1d9){
_1de="("+_1d9+") "+_1dd;
}else{
_1de=_1dd.replace(reg,"");
}
}
this.set_pagetitle(_1de);
}
};
this.new_message_focus=function(){
if(this.env.framed&&window.parent){
window.parent.focus();
}else{
window.focus();
}
};
this.add_contact_row=function(cid,cols,_1e1){
if(!this.gui_objects.contactslist||!this.gui_objects.contactslist.tBodies[0]){
return false;
}
var _1e2=this.gui_objects.contactslist.tBodies[0];
var _1e3=_1e2.rows.length;
var even=_1e3%2;
var row=document.createElement("TR");
row.id="rcmrow"+cid;
row.className="contact "+(even?"even":"odd");
if(this.contact_list.in_selection(cid)){
row.className+=" selected";
}
for(var c in cols){
col=document.createElement("TD");
col.className=String(c).toLowerCase();
col.innerHTML=cols[c];
row.appendChild(col);
}
this.contact_list.insert_row(row);
this.enable_command("export",(this.contact_list.rowcount>0));
};
this.toggle_prefer_html=function(_1e7){
var _1e8;
if(_1e8=document.getElementById("rcmfd_addrbook_show_images")){
_1e8.disabled=!_1e7.checked;
}
};
this.set_headers=function(_1e9){
if(this.gui_objects.all_headers_row&&this.gui_objects.all_headers_box&&_1e9){
var box=this.gui_objects.all_headers_box;
box.innerHTML=_1e9;
box.style.display="block";
if(this.env.framed&&parent.rcmail){
parent.rcmail.set_busy(false);
}else{
this.set_busy(false);
}
}
};
this.load_headers=function(elem){
if(!this.gui_objects.all_headers_row||!this.gui_objects.all_headers_box||!this.env.uid){
return;
}
this.set_classname(elem,"show-headers",false);
this.set_classname(elem,"hide-headers",true);
this.gui_objects.all_headers_row.style.display=bw.ie?"block":"table-row";
elem.onclick=function(){
rcmail.hide_headers(elem);
};
if(!this.gui_objects.all_headers_box.innerHTML){
this.display_message(this.get_label("loading"),"loading",true);
this.http_post("headers","_uid="+this.env.uid);
}
};
this.hide_headers=function(elem){
if(!this.gui_objects.all_headers_row||!this.gui_objects.all_headers_box){
return;
}
this.set_classname(elem,"hide-headers",false);
this.set_classname(elem,"show-headers",true);
this.gui_objects.all_headers_row.style.display="none";
elem.onclick=function(){
rcmail.load_headers(elem);
};
};
this.html2plain=function(_1ed,id){
var _1ef=new rcube_http_request();
var url=this.env.bin_path+"html2text.php";
var _1f1=this;
this.set_busy(true,"converting");
_1ef.onerror=function(o){
_1f1.http_error(o);
};
_1ef.oncomplete=function(o){
_1f1.set_text_value(o,id);
};
_1ef.POST(url,_1ed,"application/octet-stream");
};
this.set_text_value=function(_1f4,id){
this.set_busy(false);
document.getElementById(id).value=_1f4.get_text();
};
this.redirect=function(url,lock){
if(lock||lock===null){
this.set_busy(true);
}
if(this.env.framed&&window.parent){
parent.location.href=url;
}else{
location.href=url;
}
};
this.goto_url=function(_1f8,_1f9,lock){
var _1fb=_1f9?"&"+_1f9:"";
this.redirect(this.env.comm_path+"&_action="+_1f8+_1fb,lock);
};
this.http_sockets=new Array();
this.get_request_obj=function(){
for(var n=0;n<this.http_sockets.length;n++){
if(!this.http_sockets[n].busy){
return this.http_sockets[n];
}
}
var i=this.http_sockets.length;
this.http_sockets[i]=new rcube_http_request();
return this.http_sockets[i];
};
this.http_request=function(_1fe,_1ff,lock){
var _201=this.get_request_obj();
_1ff+=(_1ff?"&":"")+"_remote=1";
if(bw.safari){
_1ff+="&_ts="+(new Date().getTime());
}
if(_201){
if(lock){
this.set_busy(true);
}
var rcm=this;
_201.__lock=lock?true:false;
_201.__action=_1fe;
_201.onerror=function(o){
_1.http_error(o);
};
_201.oncomplete=function(o){
_1.http_response(o);
};
_201.GET(this.env.comm_path+"&_action="+_1fe+"&"+_1ff);
}
};
this.http_post=function(_205,_206,lock){
var _208;
if(_206&&typeof (_206)=="object"){
_206._remote=1;
}else{
_206+=(_206?"&":"")+"_remote=1";
}
if(_208=this.get_request_obj()){
if(lock){
this.set_busy(true);
}
var rcm=this;
_208.__lock=lock?true:false;
_208.__action=_205;
_208.onerror=function(o){
rcm.http_error(o);
};
_208.oncomplete=function(o){
rcm.http_response(o);
};
_208.POST(this.env.comm_path+"&_action="+_205,_206);
}
};
this.http_response=function(_20c){
var _20d=_20c.get_header("Content-Type");
if(_20d){
_20d=String(_20d).toLowerCase();
var _20e=_20d.split(";");
_20d=_20e[0];
}
if(_20c.__lock){
this.set_busy(false);
}
if(_20c.get_text()&&(_20d=="text/javascript"||_20d=="application/x-javascript")){
eval(_20c.get_text());
}
switch(_20c.__action){
case "delete":
if(this.task=="addressbook"){
var uid=this.contact_list.get_selection();
this.enable_command("compose",(uid&&this.contact_list.rows[uid]));
this.enable_command("delete","edit",(uid&&this.contact_list.rows[uid]&&this.env.address_sources&&!this.env.address_sources[this.env.source].readonly));
this.enable_command("export",(this.contact_list&&this.contact_list.rowcount>0));
}
case "moveto":
if(this.env.action=="show"){
this.command("list");
}else{
if(this.message_list){
this.message_list.init();
}
}
break;
case "purge":
case "expunge":
if(!this.env.messagecount&&this.task=="mail"){
if(this.env.contentframe){
this.show_contentframe(false);
}
this.enable_command("show","reply","reply-all","forward","moveto","delete","mark","viewsource","print","load-attachment","purge","expunge","select-all","select-none","sort",false);
}
break;
case "check-recent":
case "getunread":
case "list":
if(this.task=="mail"){
if(this.message_list&&_20c.__action=="list"){
this.msglist_select(this.message_list);
}
this.enable_command("show","expunge","select-all","select-none","sort",(this.env.messagecount>0));
this.enable_command("purge",this.purge_mailbox_test());
}else{
if(this.task=="addressbook"){
this.enable_command("export",(this.contact_list&&this.contact_list.rowcount>0));
}
}
break;
}
_20c.reset();
};
this.http_error=function(_210){
	alert('Error sending request: '+_210.url+' => HTTP '+_210.xmlhttp.status);
if(_210.__lock){
this.set_busy(false);
}
_210.reset();
_210.__lock=false;
this.display_message("Unknown Server Error!","error");
};
this.send_keep_alive=function(){
var d=new Date();
this.http_request("keep-alive","_t="+d.getTime());
};
this.check_for_recent=function(_212){
if(this.busy){
return;
}
if(_212){
this.set_busy(true,"checkingmail");
}
this.http_request("check-recent",(this.env.search_request?"_search="+this.env.search_request+"&":"")+"_t="+(new Date().getTime()),true);
};
this.get_single_uid=function(){
return this.env.uid?this.env.uid:(this.message_list?this.message_list.get_single_selection():null);
};
this.get_single_cid=function(){
return this.env.cid?this.env.cid:(this.contact_list?this.contact_list.get_single_selection():null);
};
this.get_caret_pos=function(obj){
if(typeof (obj.selectionEnd)!="undefined"){
return obj.selectionEnd;
}else{
if(document.selection&&document.selection.createRange){
var _214=document.selection.createRange();
if(_214.parentElement()!=obj){
return 0;
}
var gm=_214.duplicate();
if(obj.tagName=="TEXTAREA"){
gm.moveToElementText(obj);
}else{
gm.expand("textedit");
}
gm.setEndPoint("EndToStart",_214);
var p=gm.text.length;
return p<=obj.value.length?p:-1;
}else{
return obj.value.length;
}
}
};
this.set_caret2start=function(obj){
if(obj.createTextRange){
var _218=obj.createTextRange();
_218.collapse(true);
_218.select();
}else{
if(obj.setSelectionRange){
obj.setSelectionRange(0,0);
}
}
obj.focus();
};
this.lock_form=function(form,lock){
if(!form||!form.elements){
return;
}
var type;
for(var n=0;n<form.elements.length;n++){
type=form.elements[n];
if(type=="hidden"){
continue;
}
form.elements[n].disabled=lock;
}
};
};
function rcube_http_request(){
this.url="";
this.busy=false;
this.xmlhttp=null;
this.reset=function(){
this.onloading=function(){
};
this.onloaded=function(){
};
this.oninteractive=function(){
};
this.oncomplete=function(){
};
this.onabort=function(){
};
this.onerror=function(){
};
this.url="";
this.busy=false;
this.xmlhttp=null;
};
this.build=function(){
if(window.XMLHttpRequest){
this.xmlhttp=new XMLHttpRequest();
}else{
if(window.ActiveXObject){
try{
this.xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
}
catch(e){
this.xmlhttp=null;
}
}else{
}
}
};
this.GET=function(url){
this.build();
if(!this.xmlhttp){
this.onerror(this);
return false;
}
var _ref=this;
this.url=url;
this.busy=true;
this.xmlhttp.onreadystatechange=function(){
_ref.xmlhttp_onreadystatechange();
};
this.xmlhttp.open("GET",url,true);
this.xmlhttp.setRequestHeader("X-RoundCube-Referer",bw.get_cookie("roundcube_sessid"));
this.xmlhttp.send(null);
};
this.POST=function(url,body,_221){
if(typeof (_221)=="undefined"){
_221="application/x-www-form-urlencoded";
}
this.build();
if(!this.xmlhttp){
this.onerror(this);
return false;
}
var _222=body;
if(typeof (body)=="object"){
_222="";
for(var p in body){
_222+=(_222?"&":"")+p+"="+urlencode(body[p]);
}
}
var ref=this;
this.url=url;
this.busy=true;
this.xmlhttp.onreadystatechange=function(){
ref.xmlhttp_onreadystatechange();
};
this.xmlhttp.open("POST",url,true);
this.xmlhttp.setRequestHeader("Content-Type",_221);
this.xmlhttp.setRequestHeader("X-RoundCube-Referer",bw.get_cookie("roundcube_sessid"));
this.xmlhttp.send(_222);
};
this.xmlhttp_onreadystatechange=function(){
if(this.xmlhttp.readyState==1){
this.onloading(this);
}else{
if(this.xmlhttp.readyState==2){
this.onloaded(this);
}else{
if(this.xmlhttp.readyState==3){
this.oninteractive(this);
}else{
if(this.xmlhttp.readyState==4){
try{
if(this.xmlhttp.status==0){
this.onabort(this);
}else{
if(this.xmlhttp.status==200){
this.oncomplete(this);
}else{
this.onerror(this);
}
}
this.busy=false;
}
catch(err){
this.onerror(this);
this.busy=false;
}
}
}
}
}
};
this.get_header=function(name){
return this.xmlhttp.getResponseHeader(name);
};
this.get_text=function(){
return this.xmlhttp.responseText;
};
this.get_xml=function(){
return this.xmlhttp.responseXML;
};
this.reset();
};
function call_init(o){
window.setTimeout("if (window['"+o+"'] && window['"+o+"'].init) { "+o+".init(); }",bw.win?500:200);
};

