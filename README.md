# EmailValidator

Email Validator Service in PHP. Can be used to validate if email exists on mail server. Just not validate only syntax but all email infrastructure.

#### Usage

Must be run like a service. Calling address like this http://domain.com/validate.php?q=name@domain.com

Next you can get result using an ajax call in jQuery:

    $.ajax({
	  url: "http://domain.com/validate.php",
	  data: { q: "name@domain.com"},
	  dataType: "json"
	}).done(function(data) {
	   alert(data.is_valid);
    });