// options management - this is stored in localStorage to avoid any session management fuckery server side
var options = {
    showTrace: false,
    content: 'append'
};

var eventObj = {};

// add a few unenumerable functions to the options object
function setOption (key, value) {
    options[key] = value;
    $(eventObj).trigger(key);
    var serialized = {};
    for( var key in options ) {
        serialized[key] = options[key];
    }
    localStorage.setItem('uberdebug.options', JSON.stringify(serialized));
}

function loadOptionsFromLocalStorage() {
    var serialized = localStorage.getItem('uberdebug.options');
    if( serialized ) {
        serialized = JSON.parse( serialized );
        for( var key in serialized ) {
            options[key] = serialized[key];
        }
    }
}

loadOptionsFromLocalStorage();

setOption( 'showTrace', !options.showTrace );

// format our trace up html style
var traceTemplate = _.template("<div data-location='<%= location %>'><%= location %> <%= '' %></div>");
function traceToHtml (trace) {
    return "<div class='trace'>" +
           _.map( trace, traceTemplate ).join('') +
           "</div>";
}

// load all our templates and pre-compile
var templates = {};
$('script[type="text/html"]').each(function(){
    templates[this.id] = _.template( this.innerHTML );
});

// register our handlers for the various different types of debug messges we expect to get
var handlers = {}
handlers['php-ref'] = function (html, trace) {

    var $row = $(templates.phpref({
        html: html,
        trace: traceToHtml(trace)
    }));

    ref( $row.find('.php-ref')[0] );
    // build container
    $('#container').append($row);

};

handlers['clear'] = function () {
    $('#container').empty();
};

handlers['shjs'] = function (text, lang, trace) {

    var $row = $(templates.shjs({
        text: text, // textsh_highlightString(text, lang),
        lang: lang,
        trace: traceToHtml(trace)
    }));

    $row.find('.shjs').each(function(){
        //console.log(this);
         //sh_highlightElement(this, '.js');
    });

    // build container
    $('#container').append($row);

    // sh_highlightElement( $row.find('.sjhs')[0], lang );

}

$(function(){

    var socket = io.connect();

    socket.emit('subscribe', route);

    socket.on('debug', function (data) {
        if( handlers[data.handler] ) {
            handlers[data.handler].apply( null, data.args );
        } else {
            console.log(data);
            throw Error("Can't handle `" +data.handler+"`");
        }
    });

//
//    $('.stop').on('click', function(){
//            this.innerHTML = '=== stopped === (refresh to restart)'
//            // not sure if this loop is doing anything I'm pretty sure the disconnect is the only thing making this work.
//            for( var name in socket.$events ) {
//                socket.removeAllListeners( name );
//            }
//            socket.disconnect();
//        });
//
//    setup( socket );

});

// manage the incrementing intervals
function formatInterval(ms) {
    return Math.round(ms/100)/10 + 's ago';
}

function updateDisplay() {
    var now = new Date().getTime();
    $('.when').each(function(){
        this.innerHTML = formatInterval( now - this.getAttribute('data-when') );
    });
}

setInterval(updateDisplay, 500);