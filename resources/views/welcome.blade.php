
<!DOCTYPE html>
<html>
  <head>
    <title>API Dimensy</title>
    <!-- needed for adaptive design -->
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">
    <!-- <link rel="stylesheet" type="text/css" href="{{ asset('css/welcome-ui.scss') }}" /> -->

    <!--
    Redoc doesn't change outer page styles
    -->
    <style>
      body {
        margin: 0;
        padding: 0;
      }

      .sc-hKFxyN {
          padding-top : 0px !important;
          padding-bottom : 5px !important;
      }

      .api-content .api-info > p {
          display: none !important;
      }


      .sc-eCApnc {
          padding-top : 20px !important;
          padding-bottom : 10px !important;
      }
    </style>
  </head>
  <body>
    <redoc spec-url="{{ asset('yaml/dimensy.yaml') }}"></redoc>
    <script src="https://cdn.jsdelivr.net/npm/redoc@latest/bundles/redoc.standalone.js"> </script>
  </body>
</html>
