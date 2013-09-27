// options management - this is stored in localStorage to avoid any session management fuckery server side
var options = {
    showTrace: false,
    content: 'append'
};

var $container = $('#container');
var eventObj = {};
var numMessages = 0;

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

$(eventObj).on('socketConnection', function (e, state) {
//    console.log( state );
});

$(eventObj).on('socketConnection', function (e, state) {
    if( _.contains( ['connected', 'reconnected'], state ) ) {
        $('.stop').show();
        $('.start').hide();
    } else if ( _.contains( ['disconnect', 'connect_failed', 'reconnect_failed'], state ) ) {
        $('.start').show();
        $('.stop').hide();
    }
});

// wire up the UI
$(eventObj).on('showTrace', function () {
    var $showTrace = $('.show-trace');
    if( options.showTrace ) {
        $showTrace.html('show (t)race');
        $container.addClass('hideTrace');
    } else {
        $showTrace.html('hide (t)race');
        $container.removeClass('hideTrace');
    }
});

// append, prepend option display
$(eventObj).on('content', function () {
    if( options.content === 'append' ) {
        $('.content-append').hide();
        $('.content-prepend').show();
    } else {
        $('.content-append').show();
        $('.content-prepend').hide();
    }
});

$('.show-trace').on('click', function (e) {
    setOption( 'showTrace', !options.showTrace );
    e.preventDefault();
});

$(eventObj).on('messageRecieved', function() {
    $('.ubercontrol .badge').html(++numMessages);
});

// shortcut keys
window.addEventListener('keydown', function(e){
    switch (e.keyCode) {
        case 65: // 'a'
            setOption('content', 'append');
            return;
        case 67: // 'c'
            $(eventObj).trigger('clearAll');
            return;
        case 80: // 'p'
            setOption('content', 'prepend' );
            return;
        case 83: //
            $(eventObj).trigger('stopstartrequest');
            return;
        case 84: // 't'
            setOption('showTrace', !options.showTrace );
            return;
    }
});

// trigger events for every option to get the UI in the correct state
(function(){
    for( var key in options ) {
        $(eventObj).trigger(key);
    }
})();

$(eventObj).on('clearAll', function() {
    $container.empty();
    $('.ubercontrol .badge').html('0');
    numMessages = 0;
});


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

    var row = document.createElement('div');
    row.innerHTML = templates.phpref({
        html: html,
        trace: traceToHtml(trace),
        numMessages: numMessages
    });

    ref( row.children[1].children[0] );

    if( options.content === 'append' ) {
        $container.append(row);
    } else {
        $container.prepend(row);
    }

};

handlers['clear'] = function () {
    $(eventObj).trigger('clearAll');
};

handlers['syntaxHighlight'] = function (text, lang, trace) {

    console.log( text );
    return;

    var $row = $(templates.syntaxHighlight({
        text: text, // textsh_highlightString(text, lang),
        lang: lang,
        trace: traceToHtml(trace)
    }));

    $row.find('.shjs').each(function(){
        //console.log(this);
         //sh_highlightElement(this, '.js');
    });

    if( options.content === 'append' ) {
        $container.append($row);
    } else {
        $container.prepend($row);
    }

}

function socketFactory () {

//    var socket = io.connect(null,{'force new connection':true});
    var socket = io.connect();

    var stopCallback = function () {
        socket.disconnect();
        console.log("disconnecting");
    };

    var startCallback = function () {
        console.log("start callback");
        socket.socket.reconnect();
    };

    socket.on('connect', function () {
        $(eventObj).trigger('socketConnection', ["connected"]);
        $(eventObj).trigger('stopAddCallback', [stopCallback]);
        socket.emit('subscribe', route);
    });
    socket.on('reconnect', function () {
        $(eventObj).trigger('socketConnection', ["reconnected"]);
        $(eventObj).trigger('stopAddCallback', [stopCallback]);
    });

    socket.on('debug', function (data) {
        $(eventObj).trigger('messageRecieved');
        if( handlers[data.handler] ) {
            handlers[data.handler].apply( null, data.args );
        } else {
            // console.log(data);
            throw Error("Can't handle `" +data.handler+"`");
        }
    });

    // update the connection state
    socket.on('connecting', function () {
        $(eventObj).trigger('socketConnection', ["connecting"]);
    });
    socket.on('connect_failed', function () {
        $(eventObj).trigger('socketConnection', ["connect_failed"]);
        $(eventObj).trigger('stopRemoveCallback', [stopCallback]);
    });
    socket.on('reconnect_failed', function () {
        $(eventObj).trigger('socketConnection', ["reconnect_failed"]);
        $(eventObj).trigger('stopRemoveCallback', [stopCallback]);
    });
    socket.on('reconnecting', function () {
        $(eventObj).trigger('socketConnection', ["reconnecting"]);
    });

    socket.on('disconnect', function (e) {
        $(eventObj).trigger('socketConnection', ["disconnect"]);
        $(eventObj).trigger('stopRemoveCallback', [stopCallback]);
        $(eventObj).trigger('startAddCallback', [startCallback]);
    });

}

socketFactory();

// argh this is a pain - sockets reconnecting bullshit. This could so do with work
$(eventObj).on('stopAddCallback', function (e, callback) {
    $('.stop').on('click', callback);
    $(eventObj).on('stopstartrequest', callback);
});

$(eventObj).on('stopRemoveCallback', function (e, callback) {
    $('.stop').off('click', callback);
    $(eventObj).off('stopstartrequest', callback);
});

$(eventObj).on('startAddCallback', function (e, callback) {
    $('.start').one('click', callback);
    $(eventObj).one('stopstartrequest', callback);
});

// manage the incrementing intervals
function formatInterval(ms) {
    return Math.round(ms/100)/10 + 's ago';
}

function updateDisplay() {
    var now = new Date().getTime();
    $('.age').each(function(){
        this.innerHTML = formatInterval( now - this.getAttribute('data-when') );
    });
}

// setInterval(updateDisplay, 1000);