export default function MessageBar(props) {
    const showSpinner = props.showSpinner || false;
    const message = props.message || '';

    return (
        <div className={'publishpress-calendar-message-bar'} role="status" aria-live="polite">
            {showSpinner &&
            <span className={'dashicons dashicons-update-alt publishpress-spinner'} aria-hidden="true"/>
            }
            <span>{message}</span>
        </div>
    )
}
