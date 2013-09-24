// uberdebug

var express = require('express');
var http = require('http');
var fs = require('fs');
var _ = require('underscore');
var redis = require("redis");
var util = require('util');
var cons = require('consolidate');
var redis = require('redis');
var msg_count = 0;

var redisPub = redis.createClient();

// var bond = require('../libs/bond.js');

var app = express();
var server = http.createServer(app);

var io = require('socket.io').listen(server);

io.configure(function() {

    io.enable('browser client minification');  // send minified client
//    io.enable('browser client etag');          // apply etag caching logic based on version number
    io.enable('browser client gzip');          // gzip the file
    io.set('log level', 1);                    // reduce logging
    io.set('transports', [ 'websocket' ]);
//    io.set('heartbeat interval', 50);

});

// serve static files
app.use(express.favicon(__dirname + '/static/favicon.ico'));
app.use(express.static(__dirname+'/static'));

// make the content-type application/json
app.use( function (req, res, next) {
  req.headers['content-type'] = req.headers['content-type'] || 'application/json';
  next();
});
app.use( express.bodyParser() );

// configure underscore as our default templating lib
//app.engine('tmpl', cons.handlebars);
//app.set('view engine', 'tmpl');
app.set('views', __dirname + '/views');
app.set('view engine', 'handlebars');
app.set("view options", { layout: false });
app.engine('.tmpl', cons.handlebars);

// serve up index page and 404
app.all('/', function(req, res){
    res.send(
        404,
        { error: 'Bad URL. Try adding a channel so something like, ' + req.headers.host + '/mychannel' }
    );
});

// gets
app.get('/*', function(req, res){
    // check param
    var routeParams = _.compact( req.route.params[0].split('/') );
    if( _.any(routeParams, checkParam) ) {
        res.send(
            404,
            { error: 'Bad url. Url cannot contain "."' }
        );
    } else {
        res.header('Cache-Control', 'no-cache, private, no-store, must-revalidate, max-stale=0, post-check=0, pre-check=0');
        res.render(
            'listen.tmpl', {
                breadcrumbs: getBreadcrumbsHtml(routeParams),
                route: JSON.stringify(routeParams)
            }
        );
    }
});

// post data to server
app.post('/*', function(req, res){
    // check param
    var routeParams = _.compact( req.route.params[0].split('/') );
    if( _.any(routeParams, checkParam) ) {
        res.send(
            404,
            { error: 'Bad url. Url cannot contain "."' }
        );
    } else {
        redisPub.publish( 'uberdebug.' + routeParams.join('.'), JSON.stringify(req.body) );
        res.send( 200, "Done" );
    }
});

// parse a breadcrumb into html
var getBreadcrumbHtml = (function(){
    var t = _.template( '<li><a href="<%= url %>"><%- crumbName %></a><span class="divider">/</span></li>' );
    return function parseBreadcrumb (url, crumbName) {
        return t({url: url, crumbName: crumbName});
    };
})();

// parse a url into a collection of breadcrumbs
function getBreadcrumbsHtml (params) {
    var url = '/';
    var output = '';
    for( var i = 0, l = params.length-1 ; i < l; i++ ) {
        url += params[i]+'/';
        output += getBreadcrumbHtml( url, params[i] ) + "\n";
    }
    output += '<li class="active">' + params[i] + '</li>'
    return output;
}

function checkParam (param) {
    return param.indexOf('.') !== -1;
}

// socketIO routing
io.sockets.on('connection', function (socket) {

//    redisPubSub.on("pmessage", function (pattern, channel, messageString) {
//        if( pattern == 'uberdebug.*' ) {
//            var messageData = JSON.parse( messageString );
//            socket.emit( channel, messageData, channel );
//        }
//    });

    var redisSub = redis.createClient();
    redisSub.setMaxListeners(5);

    socket.on( 'subscribe', function (subscription) {

        subscription = _.compact(subscription);

        // check param
        if( !subscription.length || _.any(subscription, checkParam) ) {
            // either no subscription passed or subscription with a '.'
            // someone is messing with you
            return;
        }

        var pattern = 'uberdebug.' + subscription.join('.') + '*';

        redisSub.psubscribe(pattern);

        redisSub.on("pmessage", function (_pattern, channel, messageString) {
            var messageData = JSON.parse( messageString );
            socket.emit( 'debug', messageData );
        });

        setTimeout(function(){
            var debug = {
                handler: 'php-ref',
                args: [
                    // fs.readFileSync( __dirname+'/views/refExample.html').toString(),
                    '<!-- ref#0 --><div><div class="ref"><b data-input><i>&gt; </i><b data-expTxt>true</b></b><b data-output><b data-true data-tip="0">true</b></b><div><b data-row><b data-cell><b data-title>boolean</b></b></b></div></div></div>',
                    [
                        "sometrace information",
                        "more trace",
                    ]
                ]
            };
            redisPub.publish( 'uberdebug.spanner.fishgoat', JSON.stringify(debug) );
        }, 1000);


    });

    socket.on("disconnect", function() {
        redisSub.quit();
    });

});

server.listen(1025);
