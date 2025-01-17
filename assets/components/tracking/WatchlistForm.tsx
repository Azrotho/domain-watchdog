import {Button, Form, FormInstance, Input, Select, SelectProps, Space, Tag} from "antd";
import {t} from "ttag";
import {ApiOutlined, MinusCircleOutlined, PlusOutlined} from "@ant-design/icons";
import React from "react";
import {Connector} from "../../utils/api/connectors";
import {actionToColor, domainEvent} from "../search/EventTimeline";

type TagRender = SelectProps['tagRender'];

const formItemLayout = {
    labelCol: {
        xs: {span: 24},
        sm: {span: 4},
    },
    wrapperCol: {
        xs: {span: 24},
        sm: {span: 20},
    },
};

const formItemLayoutWithOutLabel = {
    wrapperCol: {
        xs: {span: 24, offset: 0},
        sm: {span: 20, offset: 4},
    },
};

export function WatchlistForm({form, connectors, onCreateWatchlist}: {
    form: FormInstance,
    connectors: (Connector & { id: string })[]
    onCreateWatchlist: (values: { domains: string[], emailTriggers: string[] }) => void
}) {
    const domainEventTranslated = domainEvent()

    const triggerTagRenderer: TagRender = (props) => {
        const {value, closable, onClose} = props;
        const onPreventMouseDown = (event: React.MouseEvent<HTMLSpanElement>) => {
            event.preventDefault();
            event.stopPropagation();
        };
        return (
            <Tag
                color={actionToColor(value)}
                onMouseDown={onPreventMouseDown}
                closable={closable}
                onClose={onClose}
                style={{marginInlineEnd: 4}}
            >
                {domainEventTranslated[value as keyof typeof domainEventTranslated]}
            </Tag>
        )
    }

    return <Form
        {...formItemLayoutWithOutLabel}
        form={form}
        onFinish={onCreateWatchlist}
        initialValues={{emailTriggers: ['last changed', 'transfer', 'expiration', 'deletion']}}
    >
        <Form.Item label={t`Name`}
                   name='name'
                   labelCol={{
                       xs: {span: 24},
                       sm: {span: 4},
                   }}
                   wrapperCol={{
                       md: {span: 12},
                       sm: {span: 20},
                   }}
        >
            <Input placeholder={t`Watchlist Name`}
                   title={t`Naming the Watchlist makes it easier to find in the list below.`}
                   autoComplete='off'
                   autoFocus
            />
        </Form.Item>
        <Form.List
            name="domains"
            rules={[
                {
                    validator: async (_, domains) => {
                        if (!domains || domains.length < 1) {
                            return Promise.reject(new Error(t`At least one domain name`));
                        }
                    },
                },
            ]}
        >
            {(fields, {add, remove}, {errors}) => (
                <>
                    {fields.map((field, index) => (
                        <Form.Item
                            {...(index === 0 ? formItemLayout : formItemLayoutWithOutLabel)}
                            label={index === 0 ? t`Domain names` : ''}
                            required={true}
                            key={field.key}
                        >
                            <Form.Item
                                {...field}
                                validateTrigger={['onChange', 'onBlur']}
                                rules={[{
                                    required: true,
                                    message: t`Required`
                                }, {
                                    pattern: /^(?=.*\.)\S*[^.\s]$/,
                                    message: t`This domain name does not appear to be valid`,
                                    max: 63,
                                    min: 2
                                }]}
                                noStyle
                            >
                                <Input placeholder={t`Domain name`} style={{width: '60%'}} autoComplete='off'/>
                            </Form.Item>
                            {fields.length > 1 ? (
                                <MinusCircleOutlined
                                    className="dynamic-delete-button"
                                    onClick={() => remove(field.name)}
                                />
                            ) : null}
                        </Form.Item>
                    ))}
                    <Form.Item>
                        <Button
                            type="dashed"
                            onClick={() => add()}
                            style={{width: '60%'}}
                            icon={<PlusOutlined/>}
                        >
                            {t`Add a Domain name`}
                        </Button>
                        <Form.ErrorList errors={errors}/>
                    </Form.Item>
                </>
            )}
        </Form.List>
        <Form.Item label={t`Tracked events`}
                   name='emailTriggers'
                   rules={[{required: true, message: t`At least one trigger`, type: 'array'}]}
                   labelCol={{
                       xs: {span: 24},
                       sm: {span: 4},
                   }}
                   wrapperCol={{
                       md: {span: 12},
                       sm: {span: 20},
                   }}
                   required
        >
            <Select
                mode="multiple"
                tagRender={triggerTagRenderer}
                style={{width: '100%'}}
                options={Object.keys(domainEventTranslated).map(e => ({
                    value: e,
                    label: domainEventTranslated[e as keyof typeof domainEventTranslated]
                }))}
            />
        </Form.Item>

        <Form.Item label={t`Connector`}
                   name='connector'
                   labelCol={{
                       xs: {span: 24},
                       sm: {span: 4},
                   }}
                   wrapperCol={{
                       md: {span: 12},
                       sm: {span: 20},
                   }}
                   help={t`Please make sure the connector information is valid to purchase a domain that may be available soon.`}
        >
            <Select showSearch
                    allowClear
                    placeholder={t`Connector`}
                    suffixIcon={<ApiOutlined/>}
                    optionFilterProp="label"
                    options={connectors.map(c => ({
                        label: `${c.provider} (${c.id})`,
                        value: c.id
                    }))}
            />
        </Form.Item>
        <Form.Item>
            <Space>
                <Button type="primary" htmlType="submit">
                    {t`Create`}
                </Button>
                <Button type="default" htmlType="reset">
                    {t`Reset`}
                </Button>
            </Space>
        </Form.Item>
    </Form>
}