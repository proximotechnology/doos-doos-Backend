<!DOCTYPE html>

<head>
    <title>Pusher Test</title>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
  <script>

    // Enable pusher logging - don't include this in production
    Pusher.logToConsole = true;

    var pusher = new Pusher('8914eaa6a16a77b67cc3', {
      cluster: 'eu'
    });

    var channel = pusher.subscribe('public-channel');
    channel.bind('form-submitted', function(data) {
      alert(JSON.stringify(data));
    });
  </script>


</head>

<body>
    <h1>Pusher Test</h1>
    <p>
        Try publishing an event to channel <code>my-channel</code>
        with event name <code>my-event</code>.
    </p>
</body>


