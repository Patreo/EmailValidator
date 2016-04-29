<?php
	function parseTelnetMsg($buffer, &$msg) {
		$code = substr($buffer, 0, 3);
		$msg  = "";
		switch (intval($code)) {
			case 101:			
				$msg = "The server is unable to connect.";
				break;
			case 111:
				$msg = "Connection refused or inability to open an SMTP stream.";
				break;				
			case 211:			
				$msg = "System status message or help reply.";
				break;	
			case 214:
				$msg = "A response to the HELP command.";
				break;	
			case 220:			
				$msg = "The server is ready.";
				break;	
			case 221:
				$msg = "The server is closing its transmission channel. It can come with side messages like \"Goodbye\" or \"Closing connection\".";
				break;	
			case 250:
				$msg = "Its typical side message is \"Requested mail action okay completed\": meaning that the server has transmitted a message.";
				break;		
			case 251:
				$msg = "\"User not local will forward\": the recipient's account is not on the present server, so it will be relayed to another.";
				break;
			case 252:
				$msg = "The server cannot verify the user, but it will try to deliver the message anyway.";
				break;
			case 354:
				$msg = "The side message can be very cryptic (\"Start mail input end <CRLF>.<CRLF>\"). It's the typical response to the DATA command.";
				break;				
			case 420:
				$msg = "\"Timeout connection problem\": there have been issues during the message transfer.";
				break;	
			case 421:
				$msg = "The service is unavailable due to a connection problem: it may refer to an exceeded limit of simultaneous connections, or a more general temporary problem.";
				break;	
			case 422:
				$msg = "The recipient's mailbox has exceeded its storage limit.";
				break;					
			case 431:
				$msg = "Not enough space on the disk, or an \"out of memory\" condition due to a file overload.";
				break;	
			case 432:
				$msg = "Typical side-message: \"The recipient's Exchange Server incoming mail queue has been stopped\".";
				break;					
			case 441:
				$msg = "The recipient's server is not responding.";
				break;
			case 442:
				$msg = "The connection was dropped during the transmission.";
				break;			
			case 446:
				$msg = "The maximum hop count was exceeded for the message: an internal loop has occurred.";
				break;
			case 447:
				$msg = "Your outgoing message timed out because of issues concerning the incoming server.";
				break;
			case 449:
				$msg = "A routing error.";
				break;
			case 450:
				$msg = "\"Requested action not taken – The user's mailbox is unavailable\". The mailbox has been corrupted or placed on an offline server, or your email hasn't been accepted for IP problems or blacklisting.";
				break;				
			case 451:
				$msg = "\"Requested action aborted – Local error in processing\". Your ISP's server or the server that got a first relay from yours has encountered a connection problem.";
				break;	
			case 452:
				$msg = "An error of your mail server, often due to an issue of the local anti-spam filter.";
				break;
			case 471:
				$msg = "An error of your mail server, often due to an issue of the local anti-spam filter.";
				break;
			case 500:
				$msg = "A syntax error: the server couldn't recognize the command.";
				break;
			case 501:
				$msg = "Another syntax error, not in the command but in its parameters or arguments.";
				break;
		}
		
		if (empty($msg)) {
			$msg = substr($buffer, 3);
		}
		
		return $code;
	}

	$email = $_GET["q"];
	$result["email"] = $email;
	
	// Check if email is well formatted
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$result["format"] = array("error" => "400", "message" => "Email format is invalid. Use follow syntax name@domain.ext");
	} else {
		$result["format"] = true;
		
		// Parse domain to extract domain name
		$domain = substr(strrchr($email, "@"), 1);
		$result["domain"] = $domain;
		
		// Check for DNS record MX in domain name
		if (!checkdnsrr($domain, 'MX')) {
			$result["MX"] = array("error" => "400", "message" => "No MX records found for domain $domain");
		} else {
			$mx = array();
			getmxrr($domain, $mx); // Get MX from DNS record
			
			foreach ($mx as $server) {		
				$ip = gethostbyname($server); // Get IP address from MX hostname
				$result["MX"][] = array("hostname" => $server, "ipaddr" => $ip);
			}
			
			// Connect to Telnet	
			$socket = fsockopen($ip, 25, $errno, $errstr); 
			if (!$socket) { 
				$result["connection"] = array("connected" => false, "error" => "400", "message" => "Can't connect to server $ip in port 25");
			} else {
				if (parseTelnetMsg(fgets($socket, 4096), $msg) != 220) {
					$result["connection"] = array("error" => "400", "message" => $msg);
				} else {
					// Say HELO
					fputs($socket, "helo $mx[0] \r\n"); 
					$buffer = fgets($socket, 4096);
					
					// Send MAIL FROM command
					fputs($socket, "mail from: postmaster@$domain \r\n"); 
					$buffer = fgets($socket, 4096);
					$from_result = parseTelnetMsg($buffer, $msg);
										
					// Send RCPT TO command
					fputs($socket, "rcpt to: $email\r\n"); 
					$buffer = fgets($socket, 4096);					
					$to_result = parseTelnetMsg($buffer, $msg);
					
					// Check if email is valid
					if ($from_result == 250 && $to_result == 250) {
						$result["connection"] = array("connected" => true);
					} else {
						$result["connection"] = array("connected" => false, "error" => $to_result, "message" => $msg);
					}
					
					// Send QUIT command
					fputs($socket, "quit \r\n"); 
				}
			}
			
			// Close Telnet connection
			fclose($socket);
		}
	}
	
	if (!isset($result["connection"])) {
		$result["connection"] = false;
	}
	
	if ($result["format"] == true && $result["connection"]["connected"] == true) {
		$result["is_valid"] = true;
	} else {
		$result["is_valid"] = false;
	}
	
	header('Content-Type: application/json');
	echo json_encode($result);
