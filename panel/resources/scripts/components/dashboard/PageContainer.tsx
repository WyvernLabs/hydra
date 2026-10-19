import React, { useEffect } from 'react';
import useSWR from 'swr';
import { useParams } from 'react-router-dom';
import http, { httpErrorToHuman } from '@/api/http';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';

interface Page {
    title: string;
    html: string;
}

/**
 * Hydra: a page written in the admin area (e.g. imprint or terms). The HTML
 * comes from the panel's Markdown renderer, which removes raw HTML and
 * unsafe links, so it can be shown as is.
 */
export default () => {
    const { slug } = useParams<{ slug: string }>();
    const { data, error, mutate } = useSWR<Page>(['/api/client/pages', slug], () =>
        http.get(`/api/client/pages/${slug}`).then(({ data }) => data.attributes)
    );

    useEffect(() => {
        if (data) document.title = data.title;
    }, [data]);

    // Spoilers open on click, like on Discord.
    const reveal = (event: React.MouseEvent | React.KeyboardEvent) => {
        const target = event.target as HTMLElement;
        if (target.classList.contains('md-spoiler')) target.classList.add('is-revealed');
    };

    if (error) {
        return <ServerError message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    return (
        <PageContentBlock title={data?.title}>
            {!data ? (
                <Spinner centered size={'large'} />
            ) : (
                <article className={'hydra-box'}>
                    {data.html ? (
                        <div
                            className={'hydra-box-body hydra-markdown'}
                            onClick={reveal}
                            onKeyDown={reveal}
                            dangerouslySetInnerHTML={{ __html: data.html }}
                        />
                    ) : (
                        <div className={'hydra-box-body hydra-markdown'}>
                            <h1>{data.title}</h1>
                        </div>
                    )}
                </article>
            )}
        </PageContentBlock>
    );
};
