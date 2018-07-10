<div class="wrap">
    <h1>{{ __( 'LTI Settings', 'pressbooks-lti-provider') }}</h1>
    <hr class="wp-header-end">
    <form method="POST" action="{{ $form_url }}" method="post">
        {!! wp_nonce_field( 'pb-lti-provider' ) !!}
        <table class="form-table">
            <tr>
                <th><label for="whitelist">{{ __('LTI2 Registration Whitelist', 'pressbooks-lti-provider') }}</label></th>
                <td>
                    <textarea name="whitelist" id="whitelist" class="widefat" rows="10">{!! esc_textarea($options['whitelist']) !!}</textarea>
                    <p>
                        <em>{{ __("If you want to limit automatic registrations to certain domains add them here, one domain per line. If the whitelist is empty then automatic registrations are disabled.", 'pressbooks-lti-provider') }}</em>
                    </p>
                </td>
            </tr>
        </table>
        <h2>{{ __( 'Sensible Defaults', 'pressbooks-lti-provider') }}</h2>
        <p>
            <em>{{ __("Pressbooks will try to match the LTI User with their email. If, however, a matching Pressbooks user is not found then:", 'pressbooks-lti-provider') }}</em>
        </p>
        <table class="form-table">
            <tr>
                <th>{{ __('Allow books to override role-mapping and Common Cartridge defaults', 'pressbooks-lti-provider') }}</th>
                <td>
                    <label><input name="book_override" id="book_override" type="radio"
                                  value="0" {!! checked( 0, $options['book_override'] ) !!} />{{ __('No', 'pressbooks-lti-provider') }}
                    </label><br/>
                    <label><input name="book_override" id="book_override" type="radio"
                                  value="1" {!! checked( 1, $options['book_override'] ) !!} />{{ __('Yes', 'pressbooks-lti-provider') }}</label>
                </td>
            </tr>
        </table>
        <table class="form-table">
            @foreach ([
                'admin_default' => __('Map Administrator to the following Pressbooks role', 'pressbooks-lti-provider'),
                'staff_default' => __('Map Staff to the following Pressbooks role', 'pressbooks-lti-provider'),
                'learner_default' => __('Map Learner to the following Pressbooks role', 'pressbooks-lti-provider'),
            ] as $id => $label)
                <tr>
                    <th><label for="{{ $id }}">{{ $label }}</label></th>
                    <td><select name="{{ $id }}" id="{{ $id }}">
                            <option value="administrator" {!! selected( $options[$id], 'administrator' ) !!} >{{ __('Administrator','pressbooks-lti-provider') }}</option>
                            <option value="editor" {!! selected( $options[$id], 'editor' ) !!} >{{ __('Editor','pressbooks-lti-provider') }}</option>
                            <option value="author" {!! selected( $options[$id], 'author' ) !!} >{{ __('Author','pressbooks-lti-provider') }}</option>
                            <option value="contributor" {!! selected( $options[$id], 'contributor' ) !!} >{{ __('Contributor','pressbooks-lti-provider') }}</option>
                            <option value="subscriber" {!! selected( $options[$id], 'subscriber' ) !!} >{{ __('Subscriber','pressbooks-lti-provider') }}</option>
                            <option value="anonymous" {!! selected( $options[$id], 'anonymous' ) !!} >{{ __('Anonymous Guest','pressbooks-lti-provider') }}</option>
                        </select>
                    </td>
                </tr>
            @endforeach
        </table>
        <table class="form-table">
            <tr>
                <th>{{ __('Appearance', 'pressbooks-lti-provider') }}</th>
                <td>
                    <label><input name="hide_navigation" id="hide_navigation" type="radio"
                                  value="0" {!! checked( 0, $options['hide_navigation'] ) !!} />{{ __('Display Pressbooks navigation elements in your LMS along with book content.', 'pressbooks-lti-provider') }}
                    </label><br/>
                    <label><input name="hide_navigation" id="hide_navigation" type="radio"
                                  value="1" {!! checked( 1, $options['hide_navigation'] ) !!} />{{ __('Display only book content in LMS.', 'pressbooks-lti-provider') }}</label>
                </td>
            </tr>
        </table>
        <h2>{{ __( 'Common Cartridge', 'pressbooks-lti-provider') }}</h2>
        <p>
            <em>{{ __("Export books as Common Cartridge files with LTI links.", 'pressbooks-lti-provider') }}</em>
        </p>
        <table class="form-table">
            <tr>
                <th>{{ __('Version', 'pressbooks-lti-provider') }}</th>
                <td>
                    <label><input name="cc_version" id="cc_version" type="radio"
                                  value="1.1" {!! checked( '1.1', $options['cc_version'] ) !!} />{{ __('1.1', 'pressbooks-lti-provider') }}
                    </label><br/>
                    <label><input name="cc_version" id="cc_version" type="radio"
                                  value="1.2" {!! checked( '1.2', $options['cc_version'] ) !!} />{{ __('1.2', 'pressbooks-lti-provider') }}
                    </label><br/>
                    <label><input name="cc_version" id="cc_version" type="radio"
                                  value="1.3" {!! checked( '1.3', $options['cc_version'] ) !!} />{{ __('1.3', 'pressbooks-lti-provider') }}
                    </label><br/>
                    <label><input name="cc_version" id="cc_version" type="radio"
                                  value="all" {!! checked( 'all', $options['cc_version'] ) !!} />{{ __('Show all export versions', 'pressbooks-lti-provider') }}</label>

                </td>
            </tr>
        </table>
        {!! get_submit_button() !!}
    </form>
</div>