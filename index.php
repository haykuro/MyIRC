<?php
	/* Configure */
	$host = 'irc.freenode.net';
	$port = 6667;

	/* DO NOT MODIFY BELOW THIS LINE.. */
	function generateRandomString($length = 10) {
	    $characters = '-0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	    $charactersLength = strlen($characters);
	    $randomString = '';
	    for ($i = 0; $i < $length; $i++) {
	        $randomString .= $characters[rand(0, $charactersLength - 1)];
	    }

	    // can't start with a number
	    while(preg_match('/^[\d]/', $randomString)) {
	    	$randomString = $this->generateRandomString();
	    }

	    return $randomString;
	}

	class IRC {
		protected $socket;

		/**
		 * Status.
		 *
		 * The bot will hop through the statuses until it reaches the end (idle)
		 * it will then just wait for commands from the user.
		 *
		 * 0 = Disconnected
		 * 1 = Connected, not identified.
		 * 2 = Connected, identified (NOT IN CHANNEL YET!).
		 * 3 = Connected, in the channel.
		 *
		 * @var int
		 **/
		protected $status = 0;

		protected $verbose = false;

		protected $masters = ['haykuro2'];

		protected $allowed_commands = [
			'QUIT'
		];

		function connect($host, $port) {
			$this->socket = fsockopen($host, $port, $errno, $errstr);

			if(!$this->socket) {
				throw new Exception(sprintf('(err: %d) - %s', $errno, $errstr));
			}
		}

		function start($host, $port, $channel, $verbose=false) {
			if($verbose) {
				$this->verbose = true;
			}

			try {
				$this->connect($host, $port);
			} catch (Exception $e) {
				printf("Failed to connect: %s\n", $e->getMessage());
				return false;
			}

			// connected.
			$this->status = 1;

			// generate a username.
			$username = generateRandomString(6);

			while(!feof($this->socket)) {
				$line_in = fgets($this->socket, 128);

				if($this->verbose) printf("<<..| %s", $line_in);

				if(in_array($this->status, [1])) {
					if(preg_match('/No Ident response/i', $line_in)) {
						$this->cmd(sprintf("USER %s * * : %s", $username, $username));
						$this->cmd(sprintf("NICK %s", $username));
					}

					if(preg_match(sprintf('/Erroneous Nickname/', $username), $line_in)) {
						$username = generateRandomString(6);
						$this->cmd(sprintf("NICK %s", $username));
					}

					if(preg_match(sprintf('/^\:%s MODE %s/', $username, $username), $line_in)) {
						$this->cmd(sprintf("JOIN %s", $channel));
						$this->status = 2;
					}
				}

				if(in_array($this->status, [2])) {
					if(preg_match(sprintf("/\:%s!.*?JOIN %s/", $username, $channel), $line_in)) {
						$this->say($channel, "what's up!");
						$this->status = 3;
					}
				}

				if (in_array($this->status, [3])) {
					// wait for commands now!
					if(preg_match('/\:(.*)?\!(.*)?\s+PRIVMSG\s+(.*)?\s+\:(.*)?[\r\n]+?$/', $line_in, $matches)) {
						// someone said something, was it a command for us to process?
						if(preg_match(sprintf("/%s: (.*)?\s+?(.*)?/", $username), $matches[4], $cmd_match)) {
							// it was a command! are they allowed to talk to me?
							if(!in_array($matches[1], $this->masters)) {
								// NO! STRANGER DANGER!
								$this->say($matches[3], sprintf("Sorry, %s, but you're not on the master list!", $matches[1]));
							} else {
								// extract cmd from PRIVMSG to execute.
								if(in_array($cmd_match[1], $this->allowed_commands)) {
									$this->say($matches[3], sprintf("Executing cmd: '%s' for %s.", $cmd_match[1], $matches[1]));
									$this->$cmd_match[1]();
								}
							}
						}
					}
				}

				// Always check for PING.
				if(preg_match('/^PING \:(.*?)[\r\n]+?$/', $line_in, $matches)) {
					var_dump($matches);
					$this->cmd(sprintf("PONG :%s", $matches[1]));
				}
			}

			fclose($this->socket);
		}

		function say($channel, $msg) {
			$this->cmd(sprintf("PRIVMSG %s :%s", $channel, $msg));
		}

		function quit() {
			$this->cmd("QUIT");
		}

		function cmd($text) {
			if($this->verbose) printf("..>>| %s\n", $text);
			return fwrite($this->socket, $text."\r\n");
		}

	}

	$bot = new IRC;
	$bot->start($host, $port, '#'.generateRandomString(), true);