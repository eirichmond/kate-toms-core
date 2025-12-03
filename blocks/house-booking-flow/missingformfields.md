## old form structure that should match the new form for named fields and options

```
<form action="/process-booking-forms" method="post" id="kate-and-toms-book-form" class="kate-and-toms-book-form">

    <div class="top_label">Names</div>

    <div class="clearfix">
        <div class="form_object">
            <span class="first-name">
                <input type="text" name="first-name" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="First" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="last-name">
                <input type="text" name="last-name" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Last" required="">
            </span>
        </div>
    </div>

    <div class="top_label">Address</div>

    <div class="clearfix">
        <div class="form_object">
            <span class="address-1">
                <input type="text" name="address-1" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Address Line 1" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="address-2">
                <input type="text" name="address-2" value="" size="40" class="wpcf7-form-control wpcf7-text" aria-invalid="false" placeholder="Address Line 2">
            </span>
        </div>
    </div>

    <div class="clearfix">
        <div class="form_object">
            <span class="address-3">
                <input type="text" name="address-3" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Town/City" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="address-4">
                <input type="text" name="address-4" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="County">
            </span>
        </div>
    </div>
    <div class="clearfix">
        <div class="form_object">
            <span class="address-5">
                <input type="text" name="address-5" value="" size="5" maxlength="9" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Post Code" required="">
            </span>
        </div>
    </div>
    <div class="top_label">Contact details</div>
    <div class="clearfix">
        <div class="form_object">
            <span class="email">
                <input type="email" name="email" value="" size="40" class="kate-and-toms-contact-form-email-inp" aria-required="true" aria-invalid="false" placeholder="Email Address" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="mobile">
                <input type="text" name="mobile" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Mobile" required="">
            </span>
        </div>
    </div>
    <div class="top_label">Number of guests</div>
    <div class="clearfix">
        <div class="form_object">
            <span class="number-of-adults">
                <input type="text" name="number-of-adults" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Number of Adults" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="number-of-children">
                <input type="text" name="number-of-children" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Number of Children 2-16" required="">
            </span>
        </div>
    </div>
    <div class="clearfix">
        <div class="form_object">
            <span class="number-of-infants">
                <input type="text" name="number-of-infants" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Number of Infants 0-2" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="number-of-pets-book">
                <input type="text" name="number-of-pets" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="false" aria-invalid="false" placeholder="Number of Dogs">
            </span>
        </div>

        <div class="petcon-book" style="display:none;">

            <div class="form_object">
                <span class="breed-of-pets">
                    <input type="text" name="breed-of-pets" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="false" aria-invalid="false" placeholder="Breed of dog(s)">
                </span>
            </div>

            <div class="form_object">
                <span class="age-of-pets">
                    <input type="text" name="age-of-pets" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="false" aria-invalid="false" placeholder="Age of dog(s)">
                </span>
            </div>

        </div>


    </div>
    <div class="top_label">Nature of stay/occasion</div>
    <div class="clearfix">
        <div class="form_object full">
            <span class="nature-of-stay">
                <!-- <input type="text" name="nature-of-stay" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="Birthday, Anniversary etc" required> -->
                <select name="nature-of-stay" class="select " aria-required="true" aria-invalid="false" required="">
                    <option value="Holiday">Holiday</option>
                    <option value="up to 29th Birthday Party">up to 29th Birthday Party</option>
                    <option value="30-40th Birthday Party">30-40th Birthday Party</option>
                    <option value="40+ Birthday Party">40+ Birthday Party</option>
                    <option value="Celebration (non-wedding)">Celebration (non-wedding)</option>
                    <option value="Hen Party">Hen Party</option>
                    <option value="Stag Party">Stag Party</option>
                    <option value="Corporate Event">Corporate Event</option>
                    <option value="Wedding">Wedding</option>
					<option value="Wedding Accommodation">Wedding Accommodation</option>
                </select>
            </span>
        </div>
    </div>
    <div class="top_label">Age range of group .approx</div>
    <div class="clearfix">
        <div class="form_object">
            <span class="age-range-from">
                <input type="text" name="age-range-from" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="from" required="">
            </span>
        </div>
        <div class="form_object">
            <span class="age-range-to">
                <input type="text" name="age-range-to" value="" size="40" class="kate-and-toms-contact-form-text-input" aria-required="true" aria-invalid="false" placeholder="to" required="">
            </span>
        </div>
    </div>
    <h3 style="margin-top:15px;">Your booking</h3>
    <p>Please check your booking details below.</p>

    <div class="booking_option_cont" style="margin:20px 0">
        <div class="booking_option" style="display:inline-block;width:80px;">House:</div>
        <div class="booking_option booking" style="display:inline-block;width:390px;margin-left:-4px;">
            <span class="property_name">
                <input type="text" name="property_name" value="Marsden Manor" size="40" class="kate-and-toms-contact-form-text-input" aria-invalid="false">
            </span>
        </div>
        <div class="booking_option" style="display:inline-block;width:80px;">Date from:</div>
        <div class="booking_option booking" style="display:inline-block;width:390px;margin-left:-4px;">
            <span class="date_from"><input type="text" name="date_from" value="Friday, 2 January 2026" size="40" class="kate-and-toms-contact-form-text-input" aria-invalid="false"></span>
        </div>
        <div class="booking_option" style="display:inline-block;width:80px;">Duration:</div>
        <div class="booking_option booking" style="display:inline-block;width:390px;margin-left:-4px;">
            <span class="period"><input type="text" name="period" value="2 night weekend" size="40" class="kate-and-toms-contact-form-text-input" aria-invalid="false"></span>
        </div>
    </div>

    <div style="display: none;">
        <input type="text" name="salutation" style="display:none">
        <input type="hidden" name="post_id" value="14767" size="40" class="kate-and-toms-contact-form-text-input" aria-invalid="false">
        <input type="hidden" id="booking_select_nonce" name="booking_select_nonce" value="9a6d0ddb3b"><input type="hidden" name="_wp_http_referer" value="/houses/marsden-manor/book/details/">    </div>

    <p>
        <script src="https://www.google.com/recaptcha/api.js" async="" defer=""></script>
        </p><div class="g-recaptcha" data-sitekey="6LeWkOsZAAAAAB_I17SCVfYYhTlTJ5WJHIylopiE"><div style="width: 304px; height: 78px;"><div><iframe title="reCAPTCHA" width="304" height="78" role="presentation" name="a-i8bgnbyd27p" frameborder="0" scrolling="no" sandbox="allow-forms allow-popups allow-same-origin allow-scripts allow-top-navigation allow-modals allow-popups-to-escape-sandbox allow-storage-access-by-user-activation" src="https://www.google.com/recaptcha/api2/anchor?ar=1&amp;k=6LeWkOsZAAAAAB_I17SCVfYYhTlTJ5WJHIylopiE&amp;co=aHR0cHM6Ly9rYXRlYW5kdG9tcy5jb206NDQz&amp;hl=en&amp;v=07cvpCr3Xe3g2ttJNUkC6W0J&amp;size=normal&amp;anchor-ms=20000&amp;execute-ms=15000&amp;cb=e8o0bjd0n0uj"></iframe></div><textarea id="g-recaptcha-response-1" name="g-recaptcha-response" class="g-recaptcha-response" style="width: 250px; height: 40px; border: 1px solid rgb(193, 193, 193); margin: 10px 25px; padding: 0px; resize: none; display: none;"></textarea></div><iframe style="display: none;"></iframe></div>
        <input type="submit" value="Book Now" class="wpcf7-form-control wpcf7-submit btn btn-success submit">
        <span class="ajax-loader"></span>
    <p></p>
    <div class="wpcf7-response-output wpcf7-display-none"></div>
    </form>
    ```
