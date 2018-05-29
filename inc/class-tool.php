<?php

namespace Pressbooks\Lti\Provider;

use IMSGlobal\LTI\Profile;
use IMSGlobal\LTI\ToolProvider;
use Pressbooks\Book;
use function \Pressbooks\Utility\str_remove_prefix;

class Tool extends ToolProvider\ToolProvider {

	/**
	 * @var Admin
	 */
	protected $admin;

	/**
	 * @var string
	 */
	protected $action;

	/**
	 * @var array
	 */
	protected $params;

	// ------------------------------------------------------------------------
	// Overrides
	// ------------------------------------------------------------------------

	/**
	 * Tool constructor.
	 * Launched by do_format()
	 * @see \Pressbooks\Lti\Provider\do_format
	 *
	 * @param \IMSGlobal\LTI\ToolProvider\DataConnector\DataConnector $data_connector
	 */
	public function __construct( $data_connector ) {
		parent::__construct( $data_connector );

		$this->debugMode = WP_DEBUG;

		$this->baseUrl = trailingslashit( home_url() );

		// Vendor details
		$this->vendor = new Profile\Item(
			'pressbooks',
			'Pressbooks',
			__( 'Powered by Pressbooks', 'pressbooks-lti-provider' ),
			'https://pressbooks.education/'
		);

		// Product details
		$plugin_info = get_plugin_data( __DIR__ . '/../pressbooks-lti-provider.php', false, false );
		$this->product = new Profile\Item(
			globally_unique_identifier( true ),
			$plugin_info['Name'],
			$plugin_info['Description'],
			$plugin_info['AuthorURI'],
			$plugin_info['Version']
		);

		// Resource handlers for Tool Provider. One $resourceHandlers[] per book. URLs must be relative.
		$launch_url = 'format/lti';
		$icon_url = str_remove_prefix( plugins_url( 'pressbooks-lti-provider/assets/dist/images/book.png' ), $this->baseUrl );
		$ask_for = [
			'User.id',
			'User.username',
			'Person.email.primary',
			'Membership.role',
		];

		$metadata = Book::getBookInformation();
		$course_name = $metadata['pb_title'] ?? $plugin_info['Name'];
		$course_description = $metadata['pb_about_50'] ?? $metadata['pb_about_140'] ?? null;

		$required_messages = [
			new Profile\Message( 'basic-lti-launch-request', $launch_url, $ask_for ),
		];
		$optional_messages = [
			new Profile\Message( 'ContentItemSelectionRequest', $launch_url, $ask_for ),
		];
		$this->resourceHandlers[] = new Profile\ResourceHandler(
			new Profile\Item( globally_unique_identifier(), $course_name, $course_description ),
			$icon_url,
			$required_messages,
			$optional_messages
		);

		// Services required by Tool Provider
		$this->requiredServices[] = new Profile\ServiceDefinition( [ 'application/vnd.ims.lti.v2.toolproxy+json' ], [ 'POST' ] );
	}

	/**
	 * Process a valid launch request
	 *
	 * Insert code here to handle incoming launches - use the user, context
	 * and resourceLink properties to access the current user, context and resource link.
	 */
	protected function onLaunch() {
			$this->initSessionVars();
			$this->setupUser( $this->user );
			$this->setupDeepLink();
	}

	/**
	 * Process a valid content-item request
	 *
	 * Insert code here to handle incoming content-item requests - use the user and context
	 * properties to access the current user and context.
	 */
	protected function onContentItem() {
		// Content Items (more than one LtiLinkItem)
		$this->ok = in_array( 'application/vnd.ims.lti.v1.contentitems+json', $this->mediaTypes, true );
		if ( $this->ok ) {
			// TODO: This specification doesn't seem widely supported?
			// https://www.imsglobal.org/lti/model/mediatype/application/vnd/ims/lti/v1/contentitems%2Bjson/index.html
		}

		// Content Item (a single LtiLinkItem)
		$this->ok = in_array( ToolProvider\ContentItem::LTI_LINK_MEDIA_TYPE, $this->mediaTypes, true ) || in_array( '*/*', $this->mediaTypes, true );
		if ( ! $this->ok ) {
			$this->reason = __( 'Return of an LTI link not offered', 'pressbooks-lti-provider' );
		} else {
			$this->ok = ! in_array( 'none', $this->documentTargets, true ) || ( count( $this->documentTargets ) > 1 );
			if ( ! $this->ok ) {
				$this->reason = __( 'No visible document target offered', 'pressbooks-lti-provider' );
			}
		}
		if ( $this->ok ) {
			$this->initSessionVars();
			$_SESSION['pb_lti_data'] = $_POST['data'] ?? null;
			$url = $this->baseUrl . 'format/lti/contentItemSubmit';
			$this->output = $this->renderContentItemForm( $url );
			return;
		}

		// Error
		$this->onError();
	}

	/**
	 * Process a valid tool proxy registration request
	 *
	 * Insert code here to handle incoming registration requests - use the user
	 * property to access the current user.
	 */
	protected function onRegister() {
		// Sanity check
		if ( empty( $this->consumer ) ) {
			$this->ok = false;
			$this->message = __( 'Invalid tool consumer.', 'pressbooks-lti-provider' );
			return;
		}
		if ( empty( $this->returnUrl ) ) {
			$this->ok = false;
			$this->message = __( 'Return URL was not set.', 'pressbooks-lti-provider' );
			return;
		}
		if ( ! $this->doToolProxyService() ) {
			$this->ok = false;
			$this->message = __( 'Could not establish proxy with consumer.', 'pressbooks-lti-provider' );
			return;
		}

		$success_args = [
			'lti_msg' => __( 'The tool has been successfully registered.', 'pressbooks-lti-provider' ),
			'tool_proxy_guid' => $this->consumer->getKey(),
			'status' => 'success',
		];
		$success_url = esc_url( add_query_arg( $success_args, $this->returnUrl ) );

		$cancel_args = [
			'lti_msg' => __( 'The tool registration has been cancelled.', 'pressbooks-lti-provider' ),
			'status' => 'failure',
		];
		$cancel_url = esc_url( add_query_arg( $cancel_args, $this->returnUrl ) );

		$this->output = $this->renderRegisterForm( $success_url, $cancel_url );
	}

	/**
	 * Process a response to an invalid request
	 *
	 * Insert code here to handle errors on incoming connections - do not expect
	 * the user, context and resourceLink properties to be populated but check the reason
	 * property for the cause of the error.  Return TRUE if the error was fully
	 * handled by this method.
	 */
	protected function onError() {
		$message = $this->message;
		if ( $this->debugMode && ! empty( $this->reason ) ) {
			$message = $this->reason;
		}
		// Display the error message from the provider's side if the consumer has not specified a URL to pass the error to.
		if ( empty( $this->returnUrl ) ) {
			$this->errorOutput = $message;
		}
	}

	// ------------------------------------------------------------------------
	// Overrides, sort of
	// ------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function getRedirectUrl() {
		return $this->redirectUrl;
	}

	/**
	 * @param Admin $admin
	 */
	public function setAdmin( Admin $admin ) {
		$this->admin = $admin;
	}

	// ------------------------------------------------------------------------
	// Chunks of (ideally) testable code
	// ------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * @param string $action
	 */
	public function setAction( $action ) {
		$this->action = $action;
	}

	/**
	 * @return array
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * @param array $params
	 */
	public function setParams( $params ) {
		$this->params = $params;
	}

	/**
	 * Initialize $_SESSION variables
	 */
	public function initSessionVars() {
		// Consumer
		$_SESSION['pb_lti_consumer_pk'] = null;
		$_SESSION['pb_lti_consumer_version'] = null;
		if ( is_object( $this->consumer ) ) {
			$_SESSION['pb_lti_consumer_pk'] = $this->consumer->getRecordId();
			$_SESSION['pb_lti_consumer_version'] = $this->consumer->ltiVersion;
		}

		// Resource
		$_SESSION['pb_lti_resource_pk'] = null;
		if ( is_object( $this->resourceLink ) ) {
			$_SESSION['pb_lti_resource_pk'] = $this->resourceLink->getRecordId();
		}

		// User
		$_SESSION['pb_lti_user_pk'] = null;
		$_SESSION['pb_lti_user_resource_pk'] = null;
		$_SESSION['pb_lti_user_consumer_pk'] = null;
		if ( is_object( $this->user ) ) {
			$_SESSION['pb_lti_user_pk'] = $this->user->getRecordId();
			if ( is_object( $this->user->getResourceLink() ) ) {
				$_SESSION['pb_lti_user_resource_pk'] = $this->user->getResourceLink()->getRecordId();
				if ( is_object( $this->user->getResourceLink()->getConsumer() ) ) {
					$_SESSION['pb_lti_user_consumer_pk'] = $this->user->getResourceLink()->getConsumer()->getRecordId();
				}
			}
		}

		// Return URL
		$_SESSION['pb_lti_return_url'] = $this->returnUrl;
	}

	/**
	 * @param \IMSGlobal\LTI\ToolProvider\User $user
	 *
	 * @throws \LogicException
	 */
	public function setupUser( $user ) {

		if ( ! is_object( $this->admin ) ) {
			throw new \LogicException( '$this->admin is not an object. It must be set before calling setupUser()' );
		}

		// Always logout before running the rest of this procedure
		wp_logout();

		// Role
		$settings = $this->admin->getBookSettings();
		if ( $user->isAdmin() ) {
			$role = $settings['admin_default'];
		} elseif ( $user->isStaff() ) {
			$role = $settings['staff_default'];
		} elseif ( $user->isLearner() ) {
			$role = $settings['learner_default'];
		} else {
			$role = 'anonymous';
		}

		// Email
		$email = trim( $user->email );
		if ( empty( $email ) ) {
			// The LMS did not give us an email address. Make one up based on the user ID.
			$email = $user->getId() . '@127.0.0.1';
		}

		// Try to match the LTI User with their email
		$wp_user = get_user_by( 'email', $email );

		// If there's no match then check if we should create a user (Anonymous Guest = No, Everything Else = Yes)
		if ( ! $wp_user && $role !== 'anonymous' ) {
			try {
				list( $user_id, $username ) = $this->createUser( $user->getId(), $email );
				$wp_user = get_userdata( $user_id );
			} catch ( \Exception $e ) {
				return; // TODO: What should we do on fail?!
			}
		}

		if ( $wp_user ) {
			// If the user does not have rights to the book, and role != Anonymous Guest, then add them to the book with appropriate role
			if ( ! is_user_member_of_blog( $wp_user->ID ) && $role !== 'anonymous' ) {
				add_user_to_blog( get_current_blog_id(), $wp_user->ID, $role );
			}
			// Login the user
			\Pressbooks\Redirect\programmatic_login( $wp_user->user_login );
		}
	}

	/**
	 * Create user (redirects if there is an error)
	 *
	 * @param string $username
	 * @param string $email
	 *
	 * @throws \Exception
	 *
	 * @return array [ (int) user_id, (string) sanitized username ]
	 */
	public function createUser( $username, $email ) {
		$i = 1;
		$unique_username = $this->sanitizeUser( $username );
		while ( username_exists( $unique_username ) ) {
			$unique_username = $this->sanitizeUser( "{$username}{$i}" );
			++$i;
		}

		$username = $unique_username;
		$email = sanitize_email( $email );

		// Attempt to generate the user and get the user id
		// we use wp_create_user instead of wp_insert_user so we can handle the error when the user being registered already exists
		$user_id = wp_create_user( $username, wp_generate_password(), $email );

		// Check if the user was actually created:
		if ( is_wp_error( $user_id ) ) {
			// there was an error during registration, redirect and notify the user:
			throw new \Exception( $user_id->get_error_message() );
		}

		remove_user_from_blog( $user_id, 1 );

		return [ $user_id, $username ];
	}

	/**
	 * Multisite has more restrictions on user login character set
	 *
	 * @see https://core.trac.wordpress.org/ticket/17904
	 *
	 * @param string $username
	 *
	 * @return string
	 */
	public function sanitizeUser( $username ) {
		$unique_username = sanitize_user( $username, true );
		$unique_username = strtolower( $unique_username );
		$unique_username = preg_replace( '/[^a-z0-9]/', '', $unique_username );
		return $unique_username;
	}

	/**
	 * Setup Deep Link
	 */
	public function setupDeepLink() {

		$params = $this->getParams();

		if ( empty( $params[0] ) ) {
			// Format: https://book/format/lti/launch
			$this->redirectUrl = home_url();
		} elseif ( empty( $params[1] ) ) {
			if ( is_numeric( $params[0] ) ) {
				// Format: https://book/format/lti/launch/123
				$url = wp_get_shortlink( $params[0] );
				if ( $url ) {
					$this->redirectUrl = $url;
				}
			} else {
				// Format: https://book/format/lti/launch/Hello%20World
				// TODO
			}
		} else {
			if ( in_array( $params[0], [ 'front-matter', 'part', 'chapter', 'back-matter' ], true ) ) {
				// Format: https://book/format/lti/launch/front-matter/introduction
				$args = [
					'name' => $params[1],
					'post_type' => $params[0],
					'post_status' => [ 'draft', 'web-only', 'private', 'publish' ],
					'numberposts' => 1,
				];
				$posts = get_posts( $args );
				if ( $posts ) {
					$this->redirectUrl = get_permalink( $posts[0]->ID );
				}
			}
		}

		if ( empty( $this->redirectUrl ) ) {
			$this->reason = __( 'Deep link was not found.', 'pressbooks-lti-provider' );
			$this->onError();
		}
	}

	/**
	 * Output a form to select a single LTI link
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function renderContentItemForm( $url ) {
		$html = blade()->render(
			'lti.selection', [
				'title' => get_bloginfo( 'name' ),
				'url' => $url,
				'book_structure' => Book::getBookStructure(),
			]
		);

		return $html;
	}

	/**
	 * Show a page with options to redirect back to consumer
	 *
	 * @param string $success_url
	 * @param string $cancel_url
	 *
	 * @return string
	 */
	public function renderRegisterForm( $success_url, $cancel_url ) {
		$html = blade()->render(
			'lti.register', [
				'title' => get_bloginfo( 'name' ),
				'success_url' => $success_url,
				'cancel_url' => $cancel_url,
			]
		);

		return $html;
	}

	/**
	 * Check ToolProxyRegistrationRequest against a whitelist
	 *
	 * @return bool
	 * @throws \LogicException
	 */
	public function validateRegistrationRequest() {
		if ( isset( $_POST['lti_message_type'] ) && $_POST['lti_message_type'] === 'ToolProxyRegistrationRequest' ) {

			if ( ! empty( $_POST['tc_profile_url'] ) ) {
				$url = $_POST['tc_profile_url'];
			} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$url = $_SERVER['HTTP_REFERER'];
			} else {
				return false;
			}

			if ( ! is_object( $this->admin ) ) {
				throw new \LogicException( '$this->admin is not an object. It must be set before calling validateRegistrationRequest()' );
			}

			$domain = wp_parse_url( $url, PHP_URL_HOST );
			$whitelist = $this->admin->getSettings()['whitelist'];
			if ( ! is_array( $whitelist ) ) {
				$whitelist = explode( "\n", $whitelist );
			}

			// Remove empty entries
			$whitelist = array_filter(
				$whitelist,
				function ( $var ) {
					if ( is_string( $var ) ) {
						$var = trim( $var );
					}
					return ! empty( $var );
				}
			);
			if ( empty( $whitelist ) ) {
				return false; // If the whitelist is empty then automatic registrations are disabled.
			}

			$domain = trim( strtolower( $domain ) );
			foreach ( $whitelist as $allowed ) {
				$allowed = trim( strtolower( $allowed ) );
				if ( $domain === $allowed ) {
					return true;
				}
				$dotted_domain = ".$allowed";
				if ( $dotted_domain === substr( $domain, -strlen( $dotted_domain ) ) ) {
					return true;
				}
			}

			return false;
		}

		// This is not even a ToolProxyRegistrationRequest, so yes, it's valid
		return true;
	}

}
