export default function Button(props) {
    let icon;

    if (props.icon) {
        icon = <span className={"dashicons dashicons-" + props.icon}></span>;
    }

    const className = 'publishpress-calendar-button ' + (props.className || '');

    return (
        <button type="button" className={className} onClick={props.onClick} aria-label={props.ariaLabel || props.label}>{icon}{props.label}</button>
    )
}
