var Recorder = {
  version: 1.13,
  swfObject: null,
  _callbacks: {},
  _events: {},
  _initialized: false,
  _flashBlockCatched: false,
  options: {},
  initialize: function(options){
    this.options = options || {};

    if(window.location.protocol === "file:"){
      throw new Error("Due to Adobe Flash restrictions it is not possible to use the Recorder through the file:// protocol. Please use an http server.");
    }

    if(!this.options.flashContainer){
      this._setupFlashContainer();
    }

    this.bind('initialized', function(){
      Recorder._initialized = true;
      if(Recorder._flashBlockCatched){
        Recorder._defaultOnHideFlash();
      }
      if(options.initialized){
        options.initialized();
      }
    });

    this.bind('showFlash', this.options.onFlashSecurity || this._defaultOnShowFlash);
    this._loadFlash();
  },

  clear: function(){
    Recorder._events = {};
  },

  record: function(options){
    options = options || {};
    this.clearBindings("recordingStart");
    this.clearBindings("recordingProgress");
    this.clearBindings("recordingCancel");

    this.bind('recordingStart',  this._defaultOnHideFlash);
    this.bind('recordingCancel', this._defaultOnHideFlash);
    // reload flash to allow mic permission dialog to show again
    this.bind('recordingCancel', this._loadFlash);

    this.bind('recordingStart',    options['start']);
    this.bind('recordingProgress', options['progress']);
    this.bind('recordingCancel',   options['cancel']);

    this.flashInterface().record();
  },

  stop: function(){
    return this.flashInterface()._stop();
  },

  play: function(options){
    options = options || {};
    this.clearBindings("playingProgress");
    this.bind('playingProgress', options['progress']);
    this.bind('playingStop', options['finished']);

    this.flashInterface()._play();
  },

  upload: function(options){
    options.audioParam = options.audioParam || "audio";
    options.params     = options.params || {};
    this.clearBindings("uploadSuccess");
    this.bind("uploadSuccess", function(responseText){
      options.success(Recorder._externalInterfaceDecode(responseText));
    });

    this.flashInterface().upload(options.url, options.audioParam, options.params);
  },

  audioData: function(newData){
    var delimiter = ";", newDataSerialized, stringData, data = [], sample;
    if(newData){
      newDataSerialized = newData.join(";");
    }
    stringData = this.flashInterface().audioData(newDataSerialized).split(delimiter);
    for(var i=0; i < stringData.length; i ++){
      sample = parseFloat(stringData[i]);
      if(!isNaN(sample)){
        data.push(sample);
      }
    }
    return data;
  },

  request: function(method, uri, contentType, data, callback){
    var callbackName = this.registerCallback(callback);
    this.flashInterface().request(method, uri, contentType, data, callbackName);
  },

  clearBindings: function(eventName){
    Recorder._events[eventName] = [];
  },

  bind: function(eventName, fn){
    if(!Recorder._events[eventName]){ Recorder._events[eventName] = [] }
    Recorder._events[eventName].push(fn);
  },

  triggerEvent: function(eventName, arg0, arg1){
    Recorder._executeInWindowContext(function(){
      if (!Recorder._events[eventName]) {
        return;
      }
      for(var i = 0, len = Recorder._events[eventName].length; i < len; i++){
        if(Recorder._events[eventName][i]){
          Recorder._events[eventName][i].apply(Recorder, [arg0, arg1]);
        }
      }
    });
  },

  triggerCallback: function(name, args){
    Recorder._executeInWindowContext(function(){
      Recorder._callbacks[name].apply(null, args);
    });
  },

  registerCallback: function(fn){
    var name = "CB" + parseInt(Math.random() * 999999, 10);
    Recorder._callbacks[name] = fn;
    return name;
  },

  flashInterface: function(){
    if(!this.swfObject){
      return null;
    }else if(this.swfObject.record){
      return this.swfObject;
    }else if(this.swfObject.children[3].record){
      return this.swfObject.children[3];
    }
  },

  _executeInWindowContext: function(fn){
    window.setTimeout(fn, 1);
  },

  _setupFlashContainer: function(){
    this.options.flashContainer = document.createElement("div");
    this.options.flashContainer.setAttribute("id", "recorderFlashContainer");
    this.options.flashContainer.setAttribute("style", "position: fixed; left: -9999px; top: -9999px; width: 230px; height: 140px; margin-left: 10px; border-top: 6px solid rgba(128, 128, 128, 0.6); border-bottom: 6px solid rgba(128, 128, 128, 0.6); border-radius: 5px 5px; padding-bottom: 1px; padding-right: 1px;");
    document.body.appendChild(this.options.flashContainer);
  },

  _clearFlash: function(){
    var flashElement = this.options.flashContainer.children[0];
    if(flashElement){
      this.options.flashContainer.removeChild(flashElement);
    }
  },

  _loadFlash: function(){
    this._clearFlash();
    var flashElement = document.createElement("div");
    flashElement.setAttribute("id", "recorderFlashObject");
    this.options.flashContainer.appendChild(flashElement);
    swfobject.embedSWF(this.options.swfSrc, "recorderFlashObject", "231", "141", "10.1.0", undefined, undefined, {allowscriptaccess: "always"}, undefined, function(e){
      if(e.success){
        Recorder.swfObject = e.ref;
        Recorder._checkForFlashBlock();
      }else{
        Recorder._showFlashRequiredDialog();
      }
    });
  },

  _defaultOnShowFlash: function(){
    var flashContainer = Recorder.options.flashContainer;
    flashContainer.style.left   = ((window.innerWidth  || document.body.offsetWidth)  / 2) - 115 + "px";
    flashContainer.style.top    = ((window.innerHeight || document.body.offsetHeight) / 2) - 70  + "px";
  },

  _defaultOnHideFlash: function(){
    var flashContainer = Recorder.options.flashContainer;
    flashContainer.style.left = "-9999px";
    flashContainer.style.top  = "-9999px";
  },

  _checkForFlashBlock: function(){
    window.setTimeout(function(){
      if(!Recorder._initialized){
        Recorder._flashBlockCatched = true;
        Recorder.triggerEvent("showFlash");
      }
    }, 500);
  },

  _showFlashRequiredDialog: function(){
    Recorder.options.flashContainer.innerHTML = "<p>Adobe Flash Player 10.1 or newer is required to use this feature.</p><p><a href='http://get.adobe.com/flashplayer' target='_top'>Get it on Adobe.com.</a></p>";
    Recorder.options.flashContainer.style.color = "white";
    Recorder.options.flashContainer.style.backgroundColor = "#777";
    Recorder.options.flashContainer.style.textAlign = "center";
    Recorder.triggerEvent("showFlash");
  },

  _externalInterfaceDecode: function(data){
    return data.replace(/%22/g, "\"").replace(/%5c/g, "\\").replace(/%26/g, "&").replace(/%25/g, "%");
  }
};
