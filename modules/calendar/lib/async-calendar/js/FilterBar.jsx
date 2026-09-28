import Select from "./Select";
import TaxonomyField from "./fields/TaxonomyField";
import { _n, sprintf } from '@wordpress/i18n';

export default function FilterBar(props) {
    const handleFilterChange = (filterName, value) => {
        const filterValue = value ? value[0].id : null;

        props.onChange(filterName, filterValue);
    }

    const handleStatusChange = (e, element, value) => {
        handleFilterChange('status', value);
    }

    const handleCategoriesChange = (e, element, value) => {
        handleFilterChange('category', value);
    }

    const handleTagsChange = (e, element, value) => {
        handleFilterChange('tag', value);
    }

    const handleAuthorsChange = (e, element, value) => {
        handleFilterChange('author', value);
    }

    const handlePostTypeChange = (e, element, value) => {
        handleFilterChange('postType', value);
    }

    const handleWeeksChange = (e, element, value) => {
        handleFilterChange('weeks', value);
    }

    const getWeeksFilterLabel = (numberOfWeeks) => {
        return sprintf(_n('%d week', '%d weeks', numberOfWeeks, 'publishpress'), numberOfWeeks);
    }

    const weeksFilterPlaceholder = getWeeksFilterLabel(props.numberOfWeeksToDisplay);

    let weeksOptions = [];
    for (let i = 1; i <= 12; i++) {
        weeksOptions.push({
            value: i,
            text: getWeeksFilterLabel(i)
        });
    }

    let statusValue   = (props.requestFilter.post_status) ? props.requestFilter.post_status : '';
    let typesValue    = (props.requestFilter.post_type) ? props.requestFilter.post_type : '';
    let weeksValue    = (props.requestFilter.weeks) ? props.requestFilter.weeks : '';
    
    let categoryOptions = [];
    let categoryValue   = '';
    if (props.requestFilter.category && props.requestFilter.category.value) {
        categoryOptions = [props.requestFilter.category];
        categoryValue   = props.requestFilter.category.value;
    }

    let postTagOptions = [];
    let postTagValue   = '';
    if (props.requestFilter.post_tag && props.requestFilter.post_tag.value) {
        postTagOptions = [props.requestFilter.post_tag];
        postTagValue   = props.requestFilter.post_tag.value;
    }
    
    let authorOptions = [];
    let authorValue   = '';
    if (props.requestFilter.post_author && props.requestFilter.post_author.value) {
        authorOptions = [props.requestFilter.post_author];
        authorValue = props.requestFilter.post_author.value;
    }

    return (
        <div className="publishpress-calendar-filter-bar">
            <Select
                placeholder={props.strings.allStatuses}
                options={props.statuses}
                value={statusValue}
                onSelect={handleStatusChange}
                onClear={handleStatusChange}/>

            <TaxonomyField placeholder={props.strings.allCategories}
                           isEditing={true}
                           ajaxUrl={props.ajaxUrl}
                           nonce={props.nonce}
                           options={categoryOptions}
                           value={categoryValue}
                           taxonomy={'category'}
                           onSelect={handleCategoriesChange}
                           onClear={handleCategoriesChange}
                           multiple={false}
                           className={'publishpress-calendar-category-filter'}/>

            <TaxonomyField placeholder={props.strings.allTags}
                           isEditing={true}
                           ajaxUrl={props.ajaxUrl}
                           nonce={props.nonce}
                           options={postTagOptions}
                           value={postTagValue}
                           taxonomy={'post_tag'}
                           onSelect={handleTagsChange}
                           onClear={handleTagsChange}
                           multiple={false}
                           className={'publishpress-calendar-tag-filter'}/>

            <Select
                placeholder={props.strings.allAuthors}
                ajaxUrl={props.ajaxUrl}
                nonce={props.nonce}
                options={authorOptions}
                value={authorValue}
                ajaxAction={'publishpress_calendar_search_authors'}
                onSelect={handleAuthorsChange}
                onClear={handleAuthorsChange}/>

            <Select
                placeholder={props.strings.allTypes}
                options={props.postTypes}
                value={typesValue}
                onSelect={handlePostTypeChange}
                onClear={handlePostTypeChange}/>

            <Select
                placeholder={weeksFilterPlaceholder}
                options={weeksOptions}
                value={weeksValue}
                onSelect={handleWeeksChange}
                onClear={handleWeeksChange}/>
        </div>
    )
}
