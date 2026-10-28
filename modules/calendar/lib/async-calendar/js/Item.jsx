import {getHourStringOnFormat, getDateInstanceFromString} from './Functions';
import ItemPopup from './ItemPopup';
import DOMPurify from "dompurify";
import { createPortal } from "&wp.element";


const $ = jQuery;

export default function Item(props) {
    const DEFAULT_TIME_FORMAT = 'g:i a';
    const DEFAULT_LABEL = props.strings.untitled;

    const calendarItem = React.useRef(null);
    const wasPopupOpened = React.useRef(false);

    const getHourString = () => {
        let timestampDate = getDateInstanceFromString(props.timestamp);

        return getHourStringOnFormat(timestampDate, props.timeFormat || DEFAULT_TIME_FORMAT);
    }

    const getClassName = (itemID) => {
        let className = 'publishpress-calendar-item post-' + itemID;

        if (props.isPopupOpened) {
            className += ' publishpress-calendar-item-opened-popup';
        }

        if (props.canMove) {
            className += ' publishpress-calendar-item-movable';
        }

        return className;
    }

    const isPopupElementOrChildrenOfPopup = (element) => {
        return $(element).hasClass('publishpress-calendar-popup')
            || $(element).parents('.publishpress-calendar-popup').length > 0;
    }

    const dispatchClickEvent = (e) => {
        if (isPopupElementOrChildrenOfPopup(e.target)) {
            return;
        }

        props.onClickItemCallback(props.id);
    }

    const dispatchKeyboardEvent = (e) => {
        // Keyboard events from the popup bubble through the React tree (even when rendered in a portal).
        // Let the popup controls handle them natively.
        if (isPopupElementOrChildrenOfPopup(e.target)) {
            return;
        }

        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            dispatchClickEvent(e);
        }
    }

    // Return the focus to the item when its popup is closed (close button, Escape) and the focus was lost.
    React.useEffect(() => {
        if (wasPopupOpened.current && !props.isPopupOpened && calendarItem.current) {
            const activeElement = document.activeElement;

            if (!activeElement || activeElement === document.body) {
                calendarItem.current.focus();
            }
        }

        wasPopupOpened.current = props.isPopupOpened;
    }, [props.isPopupOpened]);

    const iconElement = props.showIcon && props.icon ?
        <span className={'dashicons ' + props.icon} aria-hidden="true"> </span> : null;

    const timeElement = props.showTime ?
        <time className="publishpress-calendar-item-time"
              dateTime={props.timestamp}
              title={props.timestamp}>{getHourString()}</time> : null;

    const label = props.label || DEFAULT_LABEL;

    const sanitizedLabel = DOMPurify.sanitize(label);
    const plainLabel = DOMPurify.sanitize(label, {ALLOWED_TAGS: []});

    let accessibleLabel = plainLabel;
    if (props.showTime) {
        accessibleLabel = getHourString() + ' ' + accessibleLabel;
    }
    if (props.statusLabel) {
        accessibleLabel += ', ' + props.statusLabel;
    }

    let popupElement = null;

    if (props.isPopupOpened && calendarItem.current) {
        popupElement = (
            <ItemPopup target={calendarItem}
                       id={props.id}
                       title={label}
                       icon={props.icon}
                       timestamp={props.timestamp}
                       color={props.color}
                       canMove={props.canMove}
                       onMoveItemCallback={props.onMoveItemCallback}
                       data={props.isPopupOpened ? props.getPopupItemDataCallback() : null}
                       onItemActionClickCallback={props.onItemActionClickCallback}
                       ajaxUrl={props.ajaxUrl}
                       strings={props.strings}/>
        );

        // Render the popup outside the <li role="button"> to avoid nesting interactive controls inside a button.
        // The popup container is the item's offset parent, so the popup position stays the same.
        const portalContainer = calendarItem.current.offsetParent || (calendarItem.current.parentNode && calendarItem.current.parentNode.parentNode);

        if (createPortal && portalContainer) {
            popupElement = createPortal(popupElement, portalContainer);
        }
    }

    return (
        <li
            ref={calendarItem}
            className={getClassName(props.id)}
            style={{backgroundColor: props.color}}
            data-index={props.index}
            data-id={props.id}
            data-datetime={props.timestamp}
            onClick={dispatchClickEvent}
            onKeyDown={dispatchKeyboardEvent}
            role="button"
            tabIndex="0"
            aria-label={accessibleLabel}
            aria-haspopup="dialog"
            aria-expanded={props.isPopupOpened}>

            {iconElement}{timeElement}
            <span className="publishpress-calendar-item-title" dangerouslySetInnerHTML={{__html: sanitizedLabel}}></span>
            {popupElement}
        </li>
    )
}
