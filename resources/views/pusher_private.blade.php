<!DOCTYPE html>
<html>

<head>
    <title>Pusher Private Channel Test</title>
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script>
        // اكتب مفتاحك هنا
        const PUSHER_KEY = "your-pusher-key";
        const USER_ID = 1; // عدّل حسب المستخدم الحالي

        // إعداد الاتصال بـ Pusher
        const pusher = new Pusher(PUSHER_KEY, {
            cluster: 'mt1',
            authEndpoint: '/broadcasting/auth',
            auth: {
                headers: {
                    'Authorization': 'Bearer YOUR_TOKEN_HERE'
                }
            }
        });

        // الاستماع للقناة الخاصة
        const channel = pusher.subscribe(`private-notify.${USER_ID}`);

        channel.bind('new-private-notification', function (data) {
            console.log('📨 إشعار خاص:', data.message);
        });
    </script>
</head>

<body>
    <h1>اختبار إشعارات خاصة عبر Pusher</h1>
</body>

</html>
