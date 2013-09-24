"use strict";

var templates = {},
    lastRequestId,
    options = {
        clearEachRequest: true,
        addStrategy: 'appendTo',
        running: true,
    },
    $container;

// add a few unenumerable functions to the options object
Object.defineProperties(
    options,
    {
        persist: {
            value: function(){
                var serialized = {};
                for( var key in this ) {
                    serialized[key] = this[key];
                }
                localStorage.setItem('uberdebug.options', JSON.stringify(serialized));
            }
        },
        unpersist: {
            value: function(){
                var serialized = localStorage.getItem('uberdebug.options');
                if( serialized ) {
                    serialized = JSON.parse( serialized );
                    for( var key in serialized ) {
                        this[key] = serialized[key];
                    }
                }
            }
        }
    }
);

// load old page options
options.unpersist();

var request = (function(){
    var lastRequestId = null;
    var clearNow = function() {
        lastRequestId = null;
        $container.empty();
    };
    return function( messageData ) {
        console.log(messageData);
        if( messageData.requestId !== lastRequestId && options.clearEachRequest ) {
            clearNow();
        }
        lastRequestId = messageData.requestId;
    };
})();

$(function(){

    $container = $('#content');

    // 1. keep the checkbox in sync with option.clearEachRequest
    // 2. make clicking the button also effect the checkbox
    var $clearEachRequest = $('.clearEachRequest')
        .on('click', function(e){
            options.clearEachRequest = this.checked;
            options.persist();
            e.stopPropagation();
        });

    $clearEachRequest.get(0).checked = options.clearEachRequest;

    $clearEachRequest.closest('button')
        .on('click', function(){
            var cb = $clearEachRequest.get(0);
            cb.checked = !cb.checked;
            options.clearEachRequest = cb.checked;
            options.persist();
        });

    // clear now
    $('.clearNow').on('click', function(){
        $('#content').empty();
    });

    // add strategy append or prepend
    $('.addStrategy').on('click', function(){
        var value = this.innerHTML ===
        'append' ? 'prepend' : 'append';
        this.innerHTML = value;
        options.addStrategy = value + "To";
        options.persist();
    }).each(function(){
        this.innerHTML = options.addStrategy.replace( /To$/, '');
    });

});

$('script[type="text/tmpl"]').each(function(){
    templates[$(this).data('name')] = _.template( this.innerHTML );
});

function make(messageData, channel, preparationCallback) {

    request(messageData);

    var template = channel.replace(/^debug\./, '' );
    var html = templates[template](messageData);

    var $section = $(html)[options.addStrategy]( $container );

    if( _.isFunction(preparationCallback) ) {
        preparationCallback.call( $section.get(0), $section );
    }

    var $backtrace = $(templates.backtrace( messageData )).on('click', function(){
        $(this).css({
            overflow: 'visible',
            maxHeight: 'none',
        });
        console.log( messageData.backtrace );
    });
    $backtrace.prependTo( $section );

    // append clearerDiv
    $section.append("<div class='clearer'></div>");

    return $section;

}

$(function(){

    var socket = io.connect(':1025');

    $('.stop').on('click', function(){
            this.innerHTML = '=== stopped === (refresh to restart)'
            // not sure if this loop is doing anything I'm pretty sure the disconnect is the only thing making this work.
            for( var name in socket.$events ) {
                socket.removeAllListeners( name );
            }
            socket.disconnect();
        });

    setup( socket );

});

function setup( socket ) {

    // syntax highlight
    socket.on('debug.syntax_highlight', function(messageData, channel) {

        var $section = make(
            messageData,
            channel,
            function( $this) {
                $(this).find('*').andSelf()
                    .filter('[data-lang]')
                    .each(function(){
                        sh_highlightElement( this, sh_languages[this.getAttribute('data-lang')] );
                    });
            }
         );

    });

    socket.on('debug.clear', function() {
        $container.empty();
    });

    socket.on('debug.d', function(messageData, channel) {

        var $section = make(
            messageData,
            channel,
            function( $this ) {
                $this.on('mousedown',function(e){

                    var $target = $( e.originalTarget || e.target );

                    if( $target.is('a[href]') ) {
                        $( $target.attr('href') ).show();
                        $target.trigger('click');
                    }

                    // linker
                    if( e.button === 0 && $target.is('.repeat') ) {
                        var repeat = $('#'+$target.attr('repeat-id'))[0].cloneNode(true);
                        repeat.removeAttribute('id');
                        console.log( repeat );
                        $target.replaceWith( $(repeat) );
                    }

                    // show / hide
                    if( $target.is('.showhide') ) {
                        var $prev = $target.prev();
                        switch( e.button ) {
                            case 2:
                                var action = $prev.is('.hide') ? 'removeClass' : 'addClass';
                                console.log( action );
                                $prev.find('.showhide')
                                    .each(function(){
                                        $(this).prev()[action]('hide');
                                    });
                            case 0:
                                $prev.toggleClass('hide');
                                break;
                        }
                    }

                });
            }
        );

    });

    socket.on('debug.exception', function(messageData, channel) {

        make( messageData, channel );

    });

    // syntax highlight
    socket.on('debug.print_r', make);

}

/*! See, http://joncom.be/code/javascript-json-formatter/ */
function FormatJSON(oData, sIndent) {
    if (arguments.length < 2) {
        var sIndent = "";
    }
    var sIndentStyle = "    ";
    var sDataType = RealTypeOf(oData);

    // open object
    if (sDataType == "array") {
        if (oData.length == 0) {
            return "[]";
        }
        var sHTML = "[";
    } else {
        var iCount = 0;
        $.each(oData, function() {
            iCount++;
            return;
        });
        if (iCount == 0) { // object is empty
            return "{}";
        }
        var sHTML = "{";
    }

    // loop through items
    var iCount = 0;
    $.each(oData, function(sKey, vValue) {
        if (iCount > 0) {
            sHTML += ",";
        }
        if (sDataType == "array") {
            sHTML += ("\n" + sIndent + sIndentStyle);
        } else {
            sHTML += ("\n" + sIndent + sIndentStyle + "\"" + sKey + "\"" + ": ");
        }

        // display relevant data type
        switch (RealTypeOf(vValue)) {
            case "array":
            case "object":
                sHTML += FormatJSON(vValue, (sIndent + sIndentStyle));
                break;
            case "boolean":
            case "number":
                sHTML += vValue.toString();
                break;
            case "null":
                sHTML += "null";
                break;
            case "string":
                sHTML += ("\"" + vValue + "\"");
                break;
            default:
                sHTML += ("TYPEOF: " + typeof(vValue));
        }

        // loop
        iCount++;
    });

    // close object
    if (sDataType == "array") {
        sHTML += ("\n" + sIndent + "]");
    } else {
        sHTML += ("\n" + sIndent + "}");
    }

    // return
    return sHTML;
}