

// options management - this is stored in localStorage to avoid any session management fuckery server side
var options = {
    clearEachRequest: true,
    addStrategy: 'appendTo',
    running: true,
};

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

function formatInterval(ms) {
    return Math.round(ms/100)/10 + 's ago';
}

setInterval(function(){

    var now = new Date().getTime();
    $('.when').each(function(){
        this.innerHTML = formatInterval( now - this.getAttribute('data-when') );
    });

}, 1000);

