import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { CampaignProps } from '@/types/campaign';
import { useState } from 'react';
import { Statistics } from './Partials/Statistics';
import List from './Partials/List';
import { CampaignMailProps } from '@/types/campaign-mail';

export type StatisticsProps = {
  total_emails: number;
  total_opens: number;
  total_clicks: number;
  open_rate: number;
  click_rate: number;
  unique_opens: number;
  unique_clicks: number;
};

export type CampaignMailsResult = {
  data: CampaignMailProps[];
  total: number;
  current_page: number;
  per_page: number;
  next_page_url: string;
  prev_page_url: string;
  to: number;
  from: number;
};

type Props = {
  campaign: CampaignProps;
  campaignMails: CampaignMailsResult;
  statistics: StatisticsProps;
};

export default function Index({ campaign, campaignMails, statistics }: Props) {
  const [activeTab, setActiveTab] = useState('statistics');

  return (
    <AuthenticatedLayout
      header={
        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
          Lists
        </h2>
      }
    >
      <Head title="List" />

      <div className="flex flex-col gap-2">
        <Link
          href={route('dashboard')}
          className="mt-10 lg:mt-7 mb-2 ml-1 text-xs text-gray-400 w-[8rem]"
        >
          {`Campaigns > `}
          <Link
            href={route('campaign.statistics', { campaign: campaign.id })}
            className="text-gray-200"
          >
            Show
          </Link>
        </Link>
        <section
          className={`mb-8 p-5 py-7 lg:p-8 w-[90vw] lg:min-h-[28rem] max-w-[50rem] rounded-xl bg-background-secondary flex flex-col items-start justify-start`}
        >
          <div className="flex items-start justify-start gap-4 mb-8 text-left text-[0.95rem]">
            <button
              onClick={() => setActiveTab('statistics')}
              className={`${activeTab === 'statistics' ? 'text-white border-b-2 font-bold border-b-accent-blue-mid pb-1' : 'text-gray-400'}`}
            >
              Statistics
            </button>
            <button
              onClick={() => setActiveTab('opens')}
              className={`${activeTab === 'opens' ? 'text-white border-b-2 font-bold border-b-accent-blue-mid pb-1' : 'text-gray-400'}`}
            >
              Opened
            </button>
            <button
              onClick={() => setActiveTab('clicks')}
              className={`${activeTab === 'clicks' ? 'text-white border-b-2 font-bold border-b-accent-blue-mid pb-1' : 'text-gray-400'}`}
            >
              Clicked
            </button>
          </div>

          {activeTab === 'statistics' && (
            <Statistics
              statistics={statistics}
              campaignMails={campaignMails}
              campaign={campaign}
            />
          )}

          {(activeTab === 'opens' || activeTab === 'clicks') && (
            <List
              campaignMails={campaignMails}
              campaign={campaign}
              variant={activeTab}
            />
          )}
        </section>
      </div>
    </AuthenticatedLayout>
  );
}
