var XTR_STARTED=false;

var xoadIsIE = eval("/*@cc_on!@*/!1");

 function in_array(arr, value) {
   for (var i in arr) {
       if (arr[i] == value)
         return true;
   }
   return false;
 }         
    
var XOAD_ERROR_USER = 0x400;
var XOAD_ERROR_TIMEOUT = 0x401;
var xoad = {};
xoad.errorHandler = null;
xoad.callbacks = {};
xoad.callbacks.table = {};
xoad.callbacks.count = 0;
xoad.events = {};
xoad.events.table = [];
xoad.events.postTable = [];
xoad.events.timeout = 5000;
xoad.events.startInterval = 250;
xoad.events.refreshInterval = 2000;
xoad.events.status = 0;
xoad.observers = [];
xoad.asyncCall = function() {};
xoad.callSuspender = function()
{
return {
suspend : function() {
this.suspended = true;
},
suspended : false
}
};

xoad.alert=function(errorMessage)
{    
    alert(errorMessage);
};

xoad.getError = function(errorCode, errorMessage)
{
return {
code : errorCode,
message : errorMessage
}
};
xoad.getXmlHttp = function()
{
var xmlHttp = null;
try {
xmlHttp = new XMLHttpRequest();
} catch (e) {
var progIds = ['MSXML2.XMLHTTP', 'Microsoft.XMLHTTP', 'MSXML2.XMLHTTP.5.0', 'MSXML2.XMLHTTP.4.0', 'MSXML2.XMLHTTP.3.0'];
var success = false;
for (var iterator = 0; (iterator < progIds.length) && ( ! success); iterator ++) {
try {
xmlHttp = new ActiveXObject(progIds[iterator]);
success = true;
} catch (e) {}
}
if ( ! success ) {
return null;
}
}
return xmlHttp;
};
xoad.clone = function(target, source)
{
var wipeKeys = [];
var key = null;
for (key in target.__meta) {
if (typeof(source[key]) == 'undefined') {
wipeKeys[wipeKeys.length] = key;
}
}
if (wipeKeys.length > 0) {
for (var iterator = 0; iterator < wipeKeys.length; iterator ++) {
target[wipeKeys[iterator]] = null;
}
}
for (key in source.__meta) {
if (source[key] == null) {
target[key] = null;
} else {
target[key] = source[key];
}
}
target.__meta = source.__meta;
target.__size = source.__size;
target.__timeout = source.__timeout;
};
xoad.serialize = function(data)
{
if (data == null) {
return 'N;';
}
var type = typeof(data);
var code = '';
var iterator = 0;
var length = null;
var asciiCode = null;
var key = null;

if(type=='function')return '';
if (type == 'boolean') {
code += 'b:' + (data ? 1 : 0) + ';';
} else if (type == 'number') {
if (Math.round(data) == data) {
code += 'i:' + data + ';';
} else {
code += 'd:' + data + ';';
}
} else if (type == 'string') {

length = data.length;
for (iterator = 0; iterator < data.length; iterator ++) {
asciiCode = data.charCodeAt(iterator);
if ((asciiCode >= 0x00000080) && (asciiCode <= 0x000007FF)) {
length += 1;
} else if ((asciiCode >= 0x00000800) && (asciiCode <= 0x0000FFFF)) {
length += 2;
} else if ((asciiCode >= 0x00010000) && (asciiCode <= 0x001FFFFF)) {
length += 3;
} else if ((asciiCode >= 0x00200000) && (asciiCode <= 0x03FFFFFF)) {
length += 4;
} else if ((asciiCode >= 0x04000000) && (asciiCode <= 0x7FFFFFFF)) {
length += 5;
}
}
code += 's:' + length + ':"' +data + '";';
} else if (type == 'object') {
if (typeof(data.__class) == 'undefined') {
length = 0;
if (
(typeof(data.length) == 'number') &&
(data.length > 0) &&
(typeof(data[0]) != 'undefined')) {
for (iterator = 0; iterator < data.length; iterator ++) {
code += xoad.serialize(iterator);
code += xoad.serialize(data[iterator]);
}
length = data.length;
} else {
for (key in data) {
if(typeof(data[key])!='function'){
if (/^[0-9]+$/.test(key)) {
code += xoad.serialize(parseInt(key));
} else {
code += xoad.serialize(key);
}
code += xoad.serialize(data[key]);
length ++;
}
}
}
code = 'a:' + length + ':{' + code + '}';
} else {
code += 'O:' + data.__class.length + ':"' + data.__class + '":' + data.__size + ':{';
if (data.__meta != null) {
for (key in data.__meta) {
code += xoad.serialize(key);
code += xoad.serialize(data[key]);
}
}
code += '}';
}
} else {
code = 'N;'
}

return code;
};
xoad.setErrorHandler = function(handler)
{
if (
(handler != null) &&
(typeof(handler) == 'function')) {
xoad.errorHandler = handler;
return true;
}
return false;
};
xoad.restoreErrorHandler = function()
{
xoad.errorHandler = null;
return true;
};
xoad.throwException = function(error, throwArguments)
{
if (typeof(throwArguments) != 'undefined') {
var sender = throwArguments[0];
var method = throwArguments[1];
method = 'on' + method.charAt(0).toUpperCase() + method.substr(1) + 'Error';
if (xoad.invokeMethod(sender, method, [error])) {
return false;
}
}
if (
(xoad.errorHandler != null) &&
(typeof(xoad.errorHandler) == 'function')) {
xoad.errorHandler(error);
return false;
}
throw error;
};
xoad.invokeMethod = function(obj, method, invokeArguments)
{
if (
(obj == null) ||
(typeof(obj) != 'object')) {
return false;
}
var type = eval('typeof(obj.' + method + ')');
if (type == 'function') {
var invokeCode = 'obj.' + method + '(';
if (typeof(invokeArguments) != 'undefined') {
for (var iterator = 0; iterator < invokeArguments.length; iterator ++) {
invokeCode += 'invokeArguments[' + iterator + ']';
if (iterator < invokeArguments.length - 1) {
invokeCode += ', ';
}
}
}
invokeCode += ')';
return eval(invokeCode);
}
return false;
};

xoad.call = function(obj, method, callArguments)
{
if (
(obj == null) ||
(typeof(obj) != 'object') ||
(typeof(obj.__class) != 'string')) {
return false;
}
var methodCallback = null;
var methodArgs = [];
for (var iterator = 0; iterator < callArguments.length; iterator ++) {
if (
(typeof(callArguments[iterator]) == 'function') &&
(iterator == callArguments.length - 1)) {
methodCallback = callArguments[iterator];
continue;
}
methodArgs[methodArgs.length] = callArguments[iterator];
}

if(obj['result'])delete obj['result'];
if(obj['lct'])delete obj['lct'];

var xmlHttp = xoad.getXmlHttp();
var requestBody = {
source : obj,
className : obj.__class,
method : method,
arguments : methodArgs
};
xoad.notifyObservers('call', requestBody);

requestBody.source = xoad.serialize(requestBody.source);

requestBody.arguments = xoad.serialize(requestBody.arguments);
requestBody = xoad.serialize(requestBody);

var url = obj.__url;
if (url.indexOf('?') < 0) {
url += '?';
} else {
url += '&';
}
url += 'xoadCall=true';
if (methodCallback != null) {
xmlHttp.open('POST', url, true);
} else {
xmlHttp.open('POST', url, false);
}
var callId = null;
//functions added from php
obj.__clone=function(obj){xoad.clone(this,obj)};
obj.__serialize=function(){return xoad.serialize(this)};
obj.getTimeout = function(){return this.__timeout};
obj.setTimeout=function(miliseconds){this.__timeout=miliseconds};
obj.clearTimeout=function(){this.__timeout=null};
//
var callTimeout = obj.getTimeout();
if (callTimeout != null) {
callId = xoad.callbacks.count;
}
xoad.callbacks.count ++;
var callResult = true;
var requestCompleted = function() {
if (typeof(callResult) == 'object') {
if (callResult.suspended) {
     
return false;
}
}
if (callId != null) {
if (eval('xoad.callbacks.table.call' + callId + '.timeout')) {
   
return false;
}
eval('window.clearTimeout(xoad.callbacks.table.call' + callId + '.id)');
eval('xoad.callbacks.table.call' + callId + ' = null');
}
if (xmlHttp.status != 200) {
    return xoad.throwException(xoad.getError(xmlHttp.status, xmlHttp.statusText), [obj, method]);
} else {
if (xmlHttp.responseText == null) {
   
return xoad.throwException(xoad.getError(xmlHttp.status, 'Empty response.'), [obj, method]);
}

if (xmlHttp.responseText == 'SESSION_TIME_EXPIRED') 
{
        return xoad.alert('Session time expired, please login again.');
}

            
if (xmlHttp.responseText.length < 1) {
   
return xoad.throwException(xoad.getError(xmlHttp.status, 'Empty response.'), [obj, method]);
}
try {
var resp=xmlHttp.responseText;

eval('var xoadResponse = ' + resp + ';');
} catch(e) {
     
return xoad.throwException(xoad.getError(xmlHttp.status, 'Invalid response:'+xmlHttp.responseText), [obj, method]);
}
if (typeof(xoadResponse.exception) != 'undefined') {
     
return xoad.throwException(xoad.getError(XOAD_ERROR_USER, xoadResponse.exception), [obj, method]);
}
if (xoad.notifyObservers('callCompleted', xoadResponse)) {
obj.__clone(xoadResponse.returnObject);
if (typeof(xoadResponse.output) != 'undefined') {
obj.__output = xoadResponse.output;
} else {
obj.__output = null;
}

return {
returnValue : xoadResponse.returnValue,
returnObject : xoadResponse.returnObject 
};
}
}
 
return false;
};
try {

xmlHttp.setRequestHeader('Content-Type', 'text/plain; charset=UTF-8');

} catch (e) {}
if (methodCallback != null) {
xmlHttp.onreadystatechange = function() {
if (xmlHttp.readyState == 4) {
var response = requestCompleted();

if (typeof(response.returnValue) != 'undefined' || typeof(response.returnObject) != 'undefined')
{
    methodCallback(response.returnValue,response.returnObject);
}
}
}
}
if (callTimeout != null) {
eval('xoad.callbacks.table.call' + callId + ' = {}');
eval('xoad.callbacks.table.call' + callId + '.timeout = false');
eval('xoad.callbacks.table.call' + callId + '.source = obj');
eval('xoad.callbacks.table.call' + callId + '.id = '
+ 'window.setTimeout(\'xoad.callbacks.table.call' + callId + '.timeout = true; '
+ 'xoad.throwException(xoad.getError(XOAD_ERROR_TIMEOUT, "Timeout."), [xoad.callbacks.table.call' + callId + '.source, "' + method + '"]);\', callTimeout)');
}
xmlHttp.send(requestBody);
if (methodCallback == null) {
var response = requestCompleted();
if (typeof(response.returnValue) != 'undefined') 
{
 
     
return response.returnValue;
}
    
return null;
} else {
callResult = new xoad.callSuspender();



return callResult;
}
};



/*--events here--*/

xoad.addObserver = function(observer)
{
xoad.observers[xoad.observers.length] = observer;
return true;
};
xoad.notifyObservers = function(event)
{
if (xoad.observers.length < 1) {
return true;
}
var eventMethod = 'on' + event.charAt(0).toUpperCase() + event.substr(1);
var notifyArguments = [];
var iterator = 0;
for (iterator = 1; iterator < arguments.length; iterator ++) {
notifyArguments[notifyArguments.length] = arguments[iterator];
}
for (iterator = 0; iterator < xoad.observers.length; iterator ++) {
xoad.invokeMethod(xoad.observers[iterator], eventMethod, notifyArguments);
}
return true;
};





xoad.html = {};

xoad.html.onCallCompleted = function(response) {

	if (typeof(response.html) == 'string') {

		if (response.html.length > 0) {

			try {

				eval(response.html);

} catch (e) {
            }
        }
	}
};

xoad.html.exportForm = function(id,fullselect) {


if(typeof id=='object'){
    var form = id;
    } else{
    var form = document.getElementById(id);
    }

    if (form == null) {

        return null;
    }

    if (typeof(form.elements) == 'undefined') {

        return null;
    }

    var formData = {};

    for (var iterator = 0; iterator < form.elements.length; iterator ++) {

        var element = form.elements[iterator];

          
        if (element.disabled||xoad.html.hasClass(element,'xoad-ignore')) 
        {
            continue;
        }
        var elementType = element.tagName.toLowerCase();

        var elementName = null;
        var elementValue = null;

        if (
        (typeof(element.name) != 'undefined') &&
        (element.name.length > 0)) {

            elementName = element.name;

        } else if (
        (typeof(element.id) != 'undefined') &&
        (element.id.length > 0)) {

            elementName = element.id;
        }

        if (elementName != null) {

            if (elementType == 'input') {

                if (
                (element.type == 'text') ||
                (element.type == 'password') ||
                (element.type == 'button') ||
                (element.type == 'submit') ||
                (element.type == 'hidden')) {

                    elementValue = element.value;

                } else if (element.type == 'checkbox') {

                    elementValue = element.checked;

                } else if (element.type == 'radio') {

                    if (element.checked) {

                        elementValue = element.value;

                    } else {

                        try {

                            var type = eval('typeof(formData.' + elementName + ')');

                            if (type != 'undefined') {

                                continue;
                            }

                        } catch (e) {

                            continue;
                        }
                    }
                }

            } else if (elementType == 'select') {
                if (element.options.length > 0) {

                    if (element.multiple) {

                        elementName = elementName.replace(/\[\]$/ig, '');

                        elementValue = [];

                        for (var optionsIterator = 0; optionsIterator < element.options.length; optionsIterator ++) {

                            if(element.getAttribute('fullselect'))
                            {
                                elementValue[element.options[optionsIterator].value]=element.options[optionsIterator].text;
                            }
                           else{ 
                           if (element.options[optionsIterator].selected) {

                                elementValue.push(element.options[optionsIterator].value);
                            }
                           }
                        }

                    } else {

                        if (element.selectedIndex >= 0) {

                            elementValue = element.options[element.selectedIndex].value;
                        }
                    }
                }

            } else if (elementType == 'textarea') {

                elementValue = element.value;
            }
            
            
            
            dotindex=elementName.indexOf(".");
           
            if(dotindex!=-1)
            {
                elsplit=elementName.split('.');                      
                elementName= elsplit[0];
                element_pub= elsplit[1];
                dot=true;
                if(!formData[elementName])formData[elementName]={};
            }else{dot=false;}
            
            
            try {
                if(dot){
                    formData[elementName][element_pub]=elementValue;
                   }else{
                    formData[elementName]=elementValue;
                    
                }
                  //  eval('formData.' + elementName + ' = elementValue;');
                    attr=element.getAttribute('textvalue');
   
            } catch (e) {}
        }
    }

    return formData;
};


xoad.html.convertFormToUrl = function (form, prefix)
    {

        if (!prefix) prefix = '';
        if (form)
        {
            url = prefix;
            for (criteria in form)
            {
                cr = form[criteria];
                len = 0;
                for (val in cr)
                {

                    if (cr[val])
                    {
                        len++;
                    }
                    else
                    {
                        delete cr[val];
                    }
                }

                if (len > 0)
                {

                    url += '/@' + criteria + '/';
                    ex = [];
                    for (val in cr)
                    {
                        if (cr[val]) ex.push(val + '=' + cr[val]);
                    }
                    if (ex)
                    {
                        url += ex.join('&')
                    }
                }
            }

            return url;
        }
    };
    
    
    
xoad.html.hasClass=function(element, cls) 
{
    return (' ' + element.className + ' ').indexOf(' ' + cls + ' ') > -1;
};
    
xoad.html.importForm = function(id, formData) {

	if(typeof id=='object'){
    var form = id;
    } else{
    var form = document.getElementById(id);
    }

	if (
	(formData == null) ||
	(form == null)) {

		return false;
	}

	if (typeof(form.elements) == 'undefined') {

		return false;
	}

	for (var iterator = 0; iterator < form.elements.length; iterator ++) {

		var element = form.elements[iterator];

        
        
		if (element.disabled) 
        {
			continue;
		}

		var elementType = element.tagName.toLowerCase();

		var elementName = null;

		if (
		(typeof(element.name) != 'undefined') &&
		(element.name.length > 0)) {

			elementName = element.name;

		} else if (
		(typeof(element.id) != 'undefined') &&
		(element.id.length > 0)) {

			elementName = element.id;
		}
        
        


		if (elementName != null) {

			if (elementType == 'select') {

				if (element.multiple) {

					elementName = elementName.replace(/\[\]$/ig, '');
				}
			}

			var elementValue = null;

			try {

				var valueType = eval('typeof(formData["' + elementName + '"])');

				if (valueType != 'undefined') {

					elementValue = eval('formData["' + elementName+'"]');

				} else {

					continue;
				}

			} catch (e) {

				continue;
			}

            
			if (elementType == 'input') {

				if (
				(element.type == 'text') ||
				(element.type == 'password') ||
				(element.type == 'button') ||
				(element.type == 'submit') ||
				(element.type == 'hidden')) {

					element.value = elementValue;

				} else if (element.type == 'checkbox') {

					element.checked = elementValue;

				} else if (element.type == 'radio') {

					if (element.value == elementValue) {

						element.checked = true;

					} else {

						element.checked = false;
					}
				}

			} else if (elementType == 'select') {

		           if (elementValue != null) {  

					if (element.multiple) {

						element.selectedIndex = -1;

					} else {

						

						element.selectedIndex = 0;
					}
                    
                    if(typeof elementValue =='string')
                    {
                    
                        if(element.multiple)
                        {
                            
                            m_values=elementValue.split(",");
                            
                            opt=element.options;
                            
                            for (var intLoop=0; intLoop < opt.length; intLoop++) 
                            
                            {
                                    if(in_array(m_values,opt[intLoop].value))
                                    {
                                        opt[intLoop].selected=1;
                                    } 
                                 
                             }
                            
                        }else{
                            
                            element.value=elementValue;
                        }
                    
                    }else{
                    
                        groupsContainer={};
					for (var valuesIterator = 0; valuesIterator < elementValue.length; valuesIterator ++) 
                    {
                
                        
                       if(elementValue[valuesIterator].group)
                       {
                           if(!groupsContainer[elementValue[valuesIterator].group])
                               {
                                groupsContainer[elementValue[valuesIterator].group]= optGroup = document.createElement("optgroup");
                                optGroup.label=elementValue[valuesIterator].group;  
                                if(xoadIsIE){element.add(optGroup);}else{element.appendChild(optGroup);}  
                               }else{
                                 optGroup= groupsContainer[elementValue[valuesIterator].group]; 
                               }
                       } 
                       
                      opt = document.createElement("option");
                      opt.value = elementValue[valuesIterator].value ;
                      opt.text = elementValue[valuesIterator].text;
                      
                       if(elementValue[valuesIterator].group)
                       {
                         if(xoadIsIE){optGroup.add(opt);}else{optGroup.appendChild(opt);}  
                                
                       }else{                                 
                         if(xoadIsIE){element.add(opt);}else{element.appendChild(opt);}
                       }
                      
                      
                      if(elementValue[valuesIterator].selected)
                      {
                          opt.selected = elementValue[valuesIterator].selected;
                      
                      }	
                      
                      
                      	
                    }
				}
                   }

			
            } else if (elementType == 'textarea') {

				element.value = elementValue;
			}
		}
	}

	return true;
};

xoad.addObserver(xoad.html);
