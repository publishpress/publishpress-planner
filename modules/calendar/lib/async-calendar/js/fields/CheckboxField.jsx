import { __ } from '@wordpress/i18n';

export default function CheckboxField(props) {
    const editField = () => {
        return (
            <input type="checkbox"
                   id={props.id}
                   value="1"
                   checked={props.value === 'Yes'}/>
        )
    };

    const viewField = () => {
        let icon;

        if (props.value === 'Yes') {
            icon = <span id={props.id}>
                <span className="dashicons dashicons-yes-alt" aria-hidden="true"/>
                <span className="screen-reader-text">{__('Yes', 'publishpress')}</span>
            </span>;
        } else {
            icon = <span id={props.id}>
                <span className="dashicons dashicons-no-alt" aria-hidden="true"/>
                <span className="screen-reader-text">{__('No', 'publishpress')}</span>
            </span>;
        }

        return icon;
    };

    return props.isEditing ? editField() : viewField();
}
