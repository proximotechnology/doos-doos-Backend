<!DOCTYPE html>

<head>
    <title>Pusher Test</title>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script>
        // Enable pusher logging - don't include this in production
        Pusher.logToConsole = true;

        var pusher = new Pusher('ad43669c0941eab4d6f1', {
            cluster: 'eu'
        });

        var channel = pusher.subscribe('subscribe-channel');
        channel.bind('form-submitted', function(data) {
            alert(JSON.stringify(data));
        });
    </script>
</head>

<body>
    <h1>Pusher Test</h1>
    <p>
        Try publishing an event to channel <code>subscribe-channel</code>
        with event name <code>my-event</code>.
    </p>
</body>
