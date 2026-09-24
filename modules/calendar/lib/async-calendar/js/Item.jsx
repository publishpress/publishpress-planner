import {getHourStringOnFormat, getDateInstanceFromString} from './Functions';
import ItemPopup from './ItemPopup';
import DOMPurify from "dompurify";


const $ = jQuery;

export default function Item(props) {
    const DEFAULT_TIME_FORMAT = 'g:i a';
    const DEFAULT_LABEL = props.strings.untitled;

    const calendarItem = React.useRef(null);

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
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            dispatchClickEvent(e);
        }
    }

    const iconElement = props.showIcon && props.icon ?
        <span className={'dashicons ' + props.icon}> </span> : null;

    const timeElement = props.showTime ?
        <time className="publishpress-calendar-item-time"
              dateTime={props.timestamp}
              title={props.timestamp}>{getHourString()}</time> : null;

    const label = props.label || DEFAULT_LABEL;

    const sanitizedLabel = DOMPurify.sanitize(label);
    const accessibleLabel = DOMPurify.sanitize(label, {ALLOWED_TAGS: []});

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
            aria-expanded={props.isPopupOpened}>

            {iconElement}{timeElement}
            <span className="publishpress-calendar-item-title" dangerouslySetInnerHTML={{__html: sanitizedLabel}}></span>
            {props.isPopupOpened &&
            <ItemPopup target={calendarItem}
                       id={props.id}
                       title={label}
                       icon={props.icon}
                       timestamp={props.timestamp}
                       color={props.color}
                       data={props.isPopupOpened ? props.getPopupItemDataCallback() : null}
                       onItemActionClickCallback={props.onItemActionClickCallback}
                       ajaxUrl={props.ajaxUrl}
                       strings={props.strings}/>
            }
        </li>
    )
}
