<?php

namespace EDevlet;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use PHPHtmlParser\Dom;
use Psr\Http\Message\StreamInterface;
use Smalot\PdfParser\Parser;

/**
 * Class Dogrula
 * @package EDevlet
 */
class Dogrula {

	/**
   * Validation steps
	 * @var array
	 */
  protected $steps = array(
  	'parse_pdf',
    'get_token',
    'accept_form',
    'gather_link',
    'check_hash'
  );

	/**
   * Endpoint
	 * @var string
	 */
  protected $endpoint = 'https://www.turkiye.gov.tr';

	/**
   * Version
	 * @var string
	 */
  public $version = '1.0.0';

	/**
   * File to be validated
	 * @var string
	 */
  private $file;

  /**
   * Kimlik no
   * @var string
   */
  private $kimlikNo;

	/**
	 * Pdf Body
	 * @var string
	 */
  private $pdfBody;

	/**
	 * @var string
	 */
  private $pdfLink;

	/**
	 * Barkod
	 * @var string
	 */
  private $barkod;

	/**
	 * @var Client
	 */
  private $client;

	/**
	 * @var string
	 */
  private $responseBody;

	/**
	 * Token
	 * @var string
	 */
  private $token;

	/**
   * Verbose
	 * @var bool
	 */
  public $verbose = false;

	/**
	 * Log function
	 * @param $payload
	 */
  private function log() {
  	if (!$this->verbose) return;
  	echo sprintf("[VERBOSE] %s - %s" . "\r\n", date("d/m/Y H:i:s"), implode(', ', array_map(function($a) { return (string)$a; }, func_get_args())));
  }

	/**
	 * @param $tckimlik
	 *
	 * @return bool
	 */
	private function tckimlik($tckimlik){
		$olmaz=array('11111111110','22222222220','33333333330','44444444440','55555555550','66666666660','7777777770','88888888880','99999999990');
		$ilkt = $sont = $tumt = 0;
		if($tckimlik[0]==0 or !ctype_digit($tckimlik) or strlen($tckimlik)!=11){ return false;  }
		else{
			for($a=0;$a<9;$a=$a+2){ $ilkt=$ilkt+$tckimlik[$a]; }
			for($a=1;$a<9;$a=$a+2){ $sont=$sont+$tckimlik[$a]; }
			for($a=0;$a<10;$a=$a+1){ $tumt=$tumt+$tckimlik[$a]; }
			if(($ilkt*7-$sont)%10!=$tckimlik[9] or $tumt%10!=$tckimlik[10]){ return false; }
			else{
				foreach($olmaz as $olurmu){ if($tckimlik==$olurmu){ return false; } }
				return true;
			}
		}
	}

	/**
	 * Doğrulama
	 * @param string $kimlikNo
	 * @param string $file
	 *
	 * @return bool|mixed
	 */
  public function dogrula($kimlikNo, $file) {
    // Set file
    $this->file = $file;
    $this->kimlikNo = $kimlikNo;
    // Set default result to false
    $result = false;
    // Validate tckimlik
    if (!$this->tckimlik($this->kimlikNo)) {
    	$this->log('TcKimlik No Hatalı');
    	return $result;
    }
    // Run steps
    foreach ($this->steps as $step) {
      try {
	      $result = call_user_func( array( $this, $step ) );
	      if ( $result !== true ) {
		      break;
	      }
      } catch (ConnectException $e) {
	      $result = null;
	      $this->log('Bağlantı hatası tekrar deneyin');
	      break;
      } catch (\Exception $e) {
      	$result = false;
      	$this->log('Exception occured at step ' . $step, $e);
      	break;
      }
    }
    // Return result
    return $result;
  }

  function getPart($regex, $string) {
	  $m = array();
  	preg_match($regex, $string, $m);
  	return $m && isset($m[1]) ? $m[1] : null;
  }

	/**
	 * STEP1
	 * Parse pdf
	 */
	private function parse_pdf() {
  	$this->pdfBody = str_replace("\r\n", "\n", (string)(new Parser())->parseFile($this->file)->getText());
  	$this->barkod = explode("\n", $this->pdfBody);
		$this->barkod = trim(end($this->barkod));
		if (strpos($this->pdfBody, $this->kimlikNo) === false) {
			$this->log("Verilen KimlikNo Dökümanda Bulunamadı");
			return false;
		}
		if (empty($this->barkod) || !is_string($this->barkod)) {
			$this->log("Barkod bulunamadı");
			return false;
		}
		return true;
	}

	/**
	 * STEP2
	 * Get token
	 */
	private function get_token() {
		// Create client
		$this->client = new Client(array(
			'base_uri' => $this->endpoint,
			'timeout' => 5,
			'cookies' => true,
			'headers' => array(
				'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36'
			)
		));
		// Get token
		$resp = (string) $this->client->get('/belge-dogrulama')->getBody();
		$this->token = trim($this->getPart('~data-token="(.+?)"~', $resp));
		if (!$this->token || empty($this->token)) {
			$this->log('Token alınamadı');
			return false;
		}
		return true;
	}

	/**
	 * STEP3
	 * Accept Form
	 */
	private function accept_form() {
		$resp = (string) $this->client->post('/belge-dogrulama?submit', array(
			'form_params' => array(
				'sorgulananTCKimlikNo' => $this->kimlikNo,
				'sorgulananBarkod' => $this->barkod,
				'token' => $this->token
			)
		))->getBody();
		$this->token = trim($this->getPart('~data-token="(.+?)"~', $resp));
		$this->responseBody = (string) $this->client->post('/belge-dogrulama?asama=kontrol&submit', array(
			'form_params' => array(
				'chkOnay' => 1,
				'token' => $this->token
			)
		))->getBody();
		if (strpos($this->responseBody, 'pdfURL') === false) {
			$this->log('Cannot accept form');
			return false;
		}
		return true;
	}

	/**
	 * STEP4
	 * Gather Link
	 */
	private function gather_link() {
		$this->pdfLink = trim($this->getPart('~var pdfURL = \'(.+?)\';~', $this->responseBody));
		if (!$this->pdfLink || empty($this->pdfLink)) {
			$this->log('Pdf linki alınamadı');
			return false;
		}
		return true;
	}

	private function withoutLastPart($content) {
		$parts = explode('Descent', $content);
		return implode('Descent', array_slice($parts, 0, count($parts) - 1)) . 'Descent';
	}

	/**
	 * STEP5
	 * Check hashes
	 */
	private function check_hash() {
		$remoteFile = $this->client->get($this->pdfLink);
		if ($remoteFile->getStatusCode() !== 200) {
			$this->log('Uzak Pdf download edilemedi');
			return false;
		}

		$remoteFileContent = $this->withoutLastPart($remoteFile->getBody()->getContents());
		$localFileContent = $this->withoutLastPart(file_get_contents($this->file));

		return is_string($remoteFileContent) && !empty($remoteFileContent) && sha1($remoteFileContent) === sha1($localFileContent);
	}

}