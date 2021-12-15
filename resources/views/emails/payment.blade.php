<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link
      href="https://fonts.googleapis.com/css?family=Lato:100,200,300,400,500,600,700,800,900"
      rel="stylesheet"
    />
<style>
    
* {
    font-family: "Lato";
}

body {
  background: #f8fafc;
}

.rounded {
    border-radius: 0.25rem!important;
}

.bg-white {
    --bs-bg-opacity: 1;
    background-color: rgba(var(--bs-white-rgb),var(--bs-bg-opacity))!important;
}

.content {
  max-width: 500px;
  margin: auto;
  background: white;
  padding: 10px;
}

.py-3 {
    padding-top: 1rem!important;
    padding-bottom: 1rem!important;
}

.my-5 {
    margin-top: 3rem!important;
    margin-bottom: 3rem!important;
}

.text-gray {
    color: #94a3b8;
}

.fw-bold {
    font-weight: 700!important;
}

.mt-2 {
    margin-top: 0.5rem!important;
}
.justify-content-between {
    justify-content: space-between!important;
}

.d-flex {
    display: flex!important;
}

.fw-bolder {
    font-weight: bolder!important;
}

.mt-4 {
    margin-top: 1.5rem!important;
}

.align-items-center {
    align-items: center!important;
}
.justify-content-between {
    justify-content: space-between!important;
}

.fw-bold {
    font-weight: 700!important;
}

.mb-4 {
    margin-bottom: 1.5rem!important;
}

.border {
    border: 1px solid #dee2e6!important;
}

.input-group-text, select.form-control:not([size]):not([multiple]), .form-control:not(.form-control-sm):not(.form-control-lg) {
    font-size: 14px;
    padding: 0.25em;
}

.form-control:disabled, .form-control[readonly] {
    background-color: #e9ecef;
    opacity: 1;
}

.border-0 {
    border: 0!important;
}

.form-control {
    display: block;
    width: 100%;
    padding: .375rem .75rem;
    font-size: 1rem;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid #ced4da;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    border-radius: .25rem;
    transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

button, input, optgroup, select, textarea {
    margin: 0;
    font-family: inherit;
    font-size: inherit;
    line-height: inherit;
}

.mb-3 {
    margin-bottom: 1rem!important;
}

.col-auto {
    flex: 0 0 auto;
    width: auto;
}

.mt-3 {
    margin-top: 1rem!important;
}

.text-center {
    text-align: center!important;
}

.col {
    flex: 1 0 0%;
}

.btn-primary, .btn-primary:hover, .btn-primary:active, .btn-primary:visited, .btn-primary:focus {
    background-color: #029FE6 !important;
    border-color: #029FE6;
}

.btn-primary, .btn-primary.disabled {
    box-shadow: 0 2px 6px #acb5f6;
    background-color: #029fe6;
    border-color: #029fe6;
    /* margin: 20px 0 20px 0; */
}

.btn {
    font-weight: 600;
    font-size: 12px;
    line-height: 24px;
    padding: 0.3rem 0.8rem;
    letter-spacing: 0.5px;
}

.btn-primary {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
}

.btn {
    display: inline-block;
    font-weight: 400;
    line-height: 1.5;
    color: #212529;
    text-align: center;
    text-decoration: none;
    vertical-align: middle;
    cursor: pointer;
    -webkit-user-select: none;
    -moz-user-select: none;
    user-select: none;
    background-color: transparent;
    border: 1px solid transparent;
    padding: .375rem .75rem;
    font-size: 1rem;
    border-radius: .25rem;
    transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
}

.my-3 {
    margin-top: 1rem!important;
    margin-bottom: 1rem!important;
}

</style>
</head>
<body>

    <div class="content">
        <div class="col-md-4 mx-md-auto my-5 py-3 bg-white rounded">
            Hello, {{ $mailMessage->recieverName }}<br>
            <small class="text-gray">Please complete your payment before</small>
            <div class="d-flex justify-content-between fw-bold mt-2">
                <div id="deadline-date">{{ $mailMessage->date }}</div>
            </div>
            <h5 class="fw-bolder mt-4" style="font-size: 1.5rem">Payment Details</h5>
            <div class="d-flex justify-content-between align-items-center">
                <div class="fw-bold">Transfer Bank {{ $mailMessage->paymentMethod }}</div>
                @if ($mailMessage->paymentMethod == "BNI")
                    <img src="{{ asset('assets/img/logo-bank/bni.png') }}" alt="" />
                @elseif($mailMessage->paymentMethod == "Mandiri")
                    <img src="{{ asset('assets/img/logo-bank/mandiri.png') }}" alt="" />
                @elseif($mailMessage->paymentMethod == "BCA")
                    <img src="{{ asset('assets/img/logo-bank/bca.png') }}" alt="" />
                @endif                
                
            </div>

            <div class="text-gray mb-2">Bank Account Number</div>
            <div class="d-flex justify-content-between border rounded mb-4">
                <input
                    id="vaNumber"
                    type="text"
                    class="form-control border-0 bg-white"
                    style="text-align: right; font-size: 1.1em"
                    value="{{ $mailMessage->noRek }}"
                />
            </div>
            <div class="text-gray mb-2">Account Name</div>
            <div class="d-flex justify-content-between border rounded mb-4">
                <input
                    id="vaAccount"
                    type="text"
                    class="form-control border-0 bg-white"
                    value="PT. DIGITAL PRIMA SEJAHTERA"
                    disabled
                />
            </div>
            
            <div class="d-flex justify-content-between mb-3">
                <div class="col-auto">Package</div>
                <div class="col-auto"><b>{{ $mailMessage->package }}</b></div>
            </div>
            <div class="d-flex justify-content-between">
                <div class="col-auto">Payment Method</div>
                <div class="col-auto"><b>Transfer via {{ $mailMessage->paymentMethod }}</b></div>
            </div>
            <div class="d-flex justify-content-between">
                <div class="col-auto">Price inc. PPN</div>
                <div class="col-auto"><b>{{ $mailMessage->pricePPN }}</b></div>
            </div>
            <div class="d-flex justify-content-between">
                <div class="col-auto">Unique Number</div>
                <div class="col-auto"><b>{{ $mailMessage->uniqueNumber }}</b></div>
            </div>
            <div class="d-flex justify-content-between">
                <div class="col-auto">Unique Number Price inc. PPN</div>
                <div class="col-auto"><b>{{ $mailMessage->uniquePPN }}</b></div>
            </div>
            <hr />
            <div class="d-flex justify-content-between">
                <div class="col-auto"><b>TOTAL</b></div>
                <div class="col-auto"><b>{{ $mailMessage->total }}</b></div>
            </div>
        
            <div class="d-flex mt-3">
                <div class="col text-center">
                    <small
                    >Please upload your payment receipt from dashboard page.</small
                    >
                </div>
            </div>
            <div class="d-flex mt-3">
                <div class="col text-center">
                    <small
                    >After payment, wait for Dimensy's Customer Service to activate your
                    service within a maximum of 1 day.</small
                    >
                </div>
            </div>
            <div class="text-center my-3">
                <a href="https://digiprimatera.co.id/hubungi-kami">
                    Contact Us
                </a>
            </div>
        </div>
    </div>

</body>
</html>