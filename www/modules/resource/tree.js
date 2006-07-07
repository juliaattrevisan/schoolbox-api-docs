
var DIR_IMAGES = "../newimages/";
var IMG_PLUS = DIR_IMAGES + "btnPlus.gif";
var IMG_MINUS = DIR_IMAGES + "btnMinus.gif";

var imgPlus = new Image();
imgPlus.src = IMG_PLUS;
var imgMinus = new Image();
imgMinus.src = IMG_MINUS;

var objLocalTree = null;
var INDENT_WIDTH = 5;

function jsTree() {

    this.root = null;           //the root node of the tree
    this.nodes = new Array;     //array for all nodes in the tree
   
    //Constructor
    //assign to local copy of the tree
    objLocalTree = this;
}


jsTree.prototype.createRoot = function(strName) {

    //create a new node
    this.root = new jsTreeNode("", "", "", "", "", "");
    
    //assign an ID for internal tracking 
    this.root.id = "root";

    //add it into the array of all nodes 
    this.nodes["root"] = this.root;
    
    //make sure that the root is expanded
    this.root.expanded = true
    this.root.viewable = true
    //return the created node
    return this.root;
}

jsTree.prototype.buildDOM = function() {

    //call method to add root to document, which will recursively
    //add all other nodes 
    this.root.addToDOM(document.getElementById("domRoot"));

}

jsTree.prototype.toggleExpand = function(strNodeID) {

    //get the node
    var objNode = this.nodes[strNodeID];
    
    //determine whether to expand or collapse
    if (objNode.expanded)
        objNode.collapse();
    else
        objNode.expand();

}

function jsTreeNode(strID, strText, strLink) {

    //Public Properties 
    this.messageID = strID;            //the icon to display
    this.text = strText;			//body
    this.link = strLink;
	
    //Private Properties 
    this.indent = 0;                //the indent for the node
    
    //Public States
    this.expanded = false;          //is this node expanded?
 
    //Public Collections  
    this.childNodes = new Array;    //the collection of child nodes
}

jsTreeNode.prototype.addChild = function (strID, strText, strLink) {

    //create a new node 
    var objNode = new jsTreeNode(strID, strText, strLink);
    
    //assign an ID for internal tracking 
    objNode.id = strID;
    
    //assign the indent for this node
    objNode.indent = this.indent + 1;
    
    //add into the array of child nodes 
    this.childNodes[this.childNodes.length] = objNode;
    
    //add it into the array of all nodes
    objLocalTree.nodes[objNode.id] = objNode;
    
    //return the created node 
    return objNode;
}

jsTreeNode.prototype.addToDOM = function (objNodeParent) {

    //create the layer for the node
    var objNodeDiv = document.createElement("div");

    //add it to the DOM parent element
    objNodeParent.appendChild(objNodeDiv);
	
    //create string buffer for node
    var d = new jsDocument;
    
	//create string buffer for body
	var b = new jsDocument;
	
    //begin the table
    d.writeln("<table width=\"90%\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr>");
    
    //no indent needed for root or level under root
    if (this.indent > 1) {
        d.write("<td width=\"");
        d.write(this.indent * INDENT_WIDTH);
        d.write("\">&nbsp;</td>");
    }
    
    //there is no plus/minus image for the root
    if (this.indent > 0) {
    
        d.write("<td width=\"18\" align=\"center\">");
        
        //if there are children, then add a plus/minus image
        if (this.childNodes.length > 0) {
            d.write("<a href=\"javascript:objLocalTree.toggleExpand('");
            d.write(this.id);
            d.write("')\"><img src=\"");
            d.write(this.expanded ? imgMinus.src : imgPlus.src);
            d.write("\" border=\"0\" hspace=\"1\" id=\"");
            d.write("imgPM_" + this.id);
            d.write("\" /></a>");
        }
        
        d.write("</td>");
    }

    //draw the heading
    d.write("<td><b><a href=\"javascript:objLocalTree.displayBody('" + this.id + "')\" class=\"smallblack\">" + this.text + "</a></b></td>");
        d.writeln("</tr></table>");

    //assign the HTML to the layer
    objNodeDiv.innerHTML = d;

    
    //create the layer for the children 
    var objChildNodesLayer = document.createElement("div");
	objChildNodesLayer.setAttribute("id", "divChildren_" + this.id);
    objChildNodesLayer.style.position = "relative";
	objChildNodesLayer.style.display = (this.expanded ? "block" : "none");
    objNodeDiv.appendChild(objChildNodesLayer);
		    
    //call for all children 
    for (var i=0; i < this.childNodes.length; i++)
        this.childNodes[i].addToDOM(objChildNodesLayer);
}

jsTreeNode.prototype.view = function() {
    //check to see if the node is already collapsed 
    if (this.viewable) {
	      //throw an error 
        throw "Node already in view"
    } else {
            //change the state of the node 
        this.viewable = true;
		
        //hide the child nodes 
        document.getElementById("divBody_" + this.id).style.display = "block";
    }
}

jsTreeNode.prototype.hide = function() {
    //check to see if the node is already collapsed 
    if (!this.viewable) {
	      //throw an error 
        throw "Node already hidden"
    } else {

        //change the state of the node 
        this.viewable = false;

    		
        //hide the child nodes 
        document.getElementById("divBody_" + this.id).style.display = "none";
    }
}

jsTreeNode.prototype.collapse = function () {

    //check to see if the node is already collapsed 
    if (!this.expanded) {
    
        //throw an error 
        throw "Node is already collapsed"

    } else {
    
        //change the state of the node 
        this.expanded = false;
        
        //change the plus/minus image to be plus 
        document.images["imgPM_" + this.id].src = imgPlus.src;
        
        //hide the child nodes 
        document.getElementById("divChildren_" + this.id).style.display = "none";
    }
}

jsTreeNode.prototype.expand = function () {

    //check to see if the node is already expanded
    if (this.expanded) {
    
        //throw an error
        throw "Node is already expanded"
    
    } else {
    
        //change the state of the node
        this.expanded = true;
        
        //change the plus/minus image to be minus
        document.images["imgPM_" + this.id].src = imgMinus.src;
        
        //show the child nodes
        document.getElementById("divChildren_" + this.id).style.display = "block";
    }
}
