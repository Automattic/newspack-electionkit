/**
 * Popup Custom Post Type
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { withSelect, withDispatch } from '@wordpress/data';
import { compose } from '@wordpress/compose';
import { Component, Fragment } from '@wordpress/element';
import { SelectControl, TextControl } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel, PluginPostStatusInfo } from '@wordpress/edit-post';
import { ColorPaletteControl } from '@wordpress/block-editor';

const segmentsList =
  ( window && window.newspack_popups_data && window.newspack_popups_data.segments ) || [];

class ElectionKitSidebar extends Component {
  componentDidMount() {}
  componentDidUpdate( prevProps ) {}
  /**
   * Render
   */
  render() {
    const { political_party, telephone_number, onMetaFieldChange } = this.props;
    return (
      <Fragment>
        <SelectControl
          label={ __( 'Political Party', 'newspack-electionkit' ) }
          value={ political_party }
          onChange={ value => onMetaFieldChange( 'political_party', value ) }
          options={ [
            {
              value: 'democrat',
              label: __( 'Democrat', 'newspack-electionkit' ),
            },
            {
              value: 'republican',
              label: __( 'Republican', 'newspack-electionkit' ),
            },
            {
              value: 'independent',
              label: __( 'Independent', 'newspack-electionkit' ),
            },
          ] }
        />
        <TextControl
          label={ __( 'Telephone Number', 'newspack-electionkit' ) }
          value={ telephone_number }
          onChange={ value => onMetaFieldChange( 'telephone_number', value ) }
          placeholder="(111) 111-1111"
        />
      </Fragment>
    );
  }
}

const ElectionKitSidebarWithData = compose( [
  withSelect( select => {
    const { getEditedPostAttribute } = select( 'core/editor' );
    const meta = getEditedPostAttribute( 'meta' );
    const { political_party, telephone_number } = meta || {};
    return {
      political_party,
      telephone_number,
    };
  } ),
  withDispatch( dispatch => {
    return {
      onMetaFieldChange: ( key, value ) => {
        dispatch( 'core/editor' ).editPost( { meta: { [ key ]: value } } );
      },
    };
  } ),
] )( ElectionKitSidebar );

registerPlugin( 'newspack-electionkit', {
  render: () => (
    <PluginDocumentSettingPanel
      name="electionkit-settings-panel"
      title={ __( 'Profile Settings', 'newspack-electionkit' ) }
    >
      <ElectionKitSidebarWithData />
    </PluginDocumentSettingPanel>
  ),
  icon: null,
} );
